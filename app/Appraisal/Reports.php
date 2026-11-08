<?php

namespace App\Appraisal;

use App\Bank\Ledger;
use App\Bank\ProcessingException;
use App\Models\LoanFile;
use App\Models\Organization;
use App\Models\Review\Run;
use App\Services\Entitlements;
use App\Services\PrivateStorage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Reports
{
    public function snapshot(Run $r, int $actor): array
    {
        $calc = Store::latest($r, 'calculation');
        $data = ['title' => 'MortgageLens appraisal and rental review', 'scope' => $r->context, 'input_versions' => $r->inputs, 'documents' => array_merge(Store::rows($r, 'document'), Store::rows($r, 'classification_review'), Store::rows($r, 'document_batch')), 'checklist' => $r->checklist, 'facts' => Store::facts($r), 'leases' => array_merge(Store::rows($r, 'unit_match'), Store::rows($r, 'lease_candidate'), Store::rows($r, 'rent_comparison'), Store::rows($r, 'rental_scope')), 'calculation' => $calc['data'] ?? null, 'findings' => Store::findings($r), 'sources' => array_merge(Store::rows($r, 'page'), Store::rows($r, 'image')), 'notes' => Store::rows($r, 'note'), 'decisions' => array_merge(Store::rows($r, 'confirmation'), Store::rows($r, 'finding_review')), 'revisions' => Store::rows($r, 'revision'), 'run_id' => $r->id, 'actor' => $actor, 'issued_at' => now()->timezone('Asia/Dhaka')->format('Y-m-d H:i:s').' Asia/Dhaka (UTC+06:00)', 'report_version' => count(Store::rows($r, 'report')) + 1, 'processing_status' => $r->status, 'processing_error' => $r->error, 'status' => 'review_report_with_open_issues', 'notice' => 'Not loan approval, eligibility, appraisal certification, condition clearance or underwriting sign-off. Open issues and provisional values remain visible. Signature presence is not authenticity. Specialist appraisal forensic coverage unavailable.'];
        foreach ($data['sources'] as &$source) {
            unset($source['data']['disk'],$source['data']['storage_key']);
        }unset($source);
        $file = LoanFile::findOrFail($r->loan_file_id);
        $identity = [];
        foreach ($data['facts'] as $fact) {
            if (in_array($fact['data']['key'], ['address', 'parcel', 'legal_description', 'property_type', 'units'], true)) {
                $identity[$fact['data']['key']][] = ['value' => isset($fact['confirmation']) ? $fact['confirmation']['value'] : $fact['data']['normalized'], 'status' => isset($fact['confirmation']) ? 'reviewer_confirmed' : $fact['data']['status'], 'source' => 'Original version '.$fact['data']['document_version_id'].' / page '.$fact['data']['page']];
            }
        }
        $data['property_loan_summary'] = ['property_identity' => $identity, 'file' => $file->name, 'loan_number' => $file->reference, 'borrower' => $file->borrower->name, 'loan_context' => $r->context, 'appraised_value' => $calc['data']['appraised_value'] ?? null, 'selected_value_basis' => $calc['data']['selected_value_basis'] ?? null];
        foreach (['facts', 'documents', 'leases', 'findings', 'sources', 'notes', 'decisions', 'revisions'] as $section) {
            $data[$section] = array_map(fn ($row) => $this->pack($row), $data[$section]);
        }
        $data['hash'] = Ledger::hash($data);

        $generated = [];
        foreach (['pdf', 'xlsx'] as $format) {
            $generated[$format] = $this->generate($data, $format);
        }

        return DB::transaction(function () use ($r, $data, $generated) {
            $org = Organization::lockForUpdate()->findOrFail($r->organization_id);
            $storage = app(PrivateStorage::class);
            $disk = $storage->disk();
            Entitlements::storage($org, array_sum(array_map(fn ($g) => strlen($g[0]), $generated)));
            $report = Store::add($r, 'report', $data['hash'], $data);
            foreach ($generated as $format => [$bytes,$mime]) {
                $key = 'review-reports/'.$r->organization_id.'/'.$r->loan_file_id.'/'.$r->id.'/'.$data['hash'].'.'.$format;
                $storage->put($key, $bytes);
                Store::add($r, 'report_artifact', $report['id'].':'.$format, ['report_id' => $report['id'], 'format' => $format, 'mime' => $mime, 'disk' => $disk, 'storage_key' => $key, 'sha256' => hash('sha256', $bytes), 'artifact_bytes' => strlen($bytes)]);
            }

            return $report;
        });
    }

    private function pack(array $row): array
    {
        if (isset($row['data']) && isset($row['id'])) {
            return ['record_id' => $row['id'], 'recorded_at' => isset($row['created_at']) ? Carbon::parse($row['created_at'], 'UTC')->timezone('Asia/Dhaka')->format('Y-m-d H:i:s').' Asia/Dhaka (UTC+06:00)' : null] + $this->pack($row['data']) + array_intersect_key($row, array_flip(['status', 'confirmation', 'review']));
        }
        $out = [];
        foreach ($row as $key => $v) {
            if (in_array($key, ['disk', 'storage_key'], true)) {
                continue;
            }$out[$key] = is_array($v) ? $this->pack($v) : $v;
        }

        return $out;
    }

    private function flatten($value, string $prefix = ''): array
    {
        $rows = [];
        foreach ((array) $value as $key => $v) {
            $label = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($v)) {
                $rows = array_merge($rows, $this->flatten($v, $label));
            } else {
                $text = $v === null ? 'UNKNOWN' : (is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v);
                if (is_string($v) && ! str_contains($v, 'Asia/Dhaka') && preg_match('/(^|\.)(at|created_at|updated_at|issued_at|approved_at|recorded_at)$/', $label) && preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:/', $v)) {
                    $text = Carbon::parse($v, 'UTC')->timezone('Asia/Dhaka')->format('Y-m-d H:i:s').' Asia/Dhaka (UTC+06:00)';
                }
                $parts = mb_str_split($text, 700);
                foreach ($parts as $part => $text) {
                    $rows[] = [$label.(count($parts) > 1 ? ' [part '.($part + 1).'/'.count($parts).']' : ''), $text];
                }
            }
        }

        return $rows;
    }

    public function generate(array $r, string $format): array
    {
        if ($format === 'pdf') {
            $options = new Options;
            $options->setIsRemoteEnabled(false);
            $options->setIsPhpEnabled(false);
            $pdf = new Dompdf($options);
            $html = '<html><head><style>@page{margin:34px}body{font:10px DejaVu Sans;color:#15302d}h1{font-size:20px}h2{font-size:14px;page-break-after:avoid}table{width:100%;border-collapse:collapse;table-layout:fixed}td{border-bottom:1px solid #ddd;padding:5px;overflow-wrap:anywhere;word-wrap:break-word;vertical-align:top}tr{page-break-inside:avoid}td:first-child{width:40%}</style></head><body><h1>MortgageLens appraisal / rental review</h1><p>'.htmlspecialchars($r['notice']).'</p>';
            $c = $r['calculation'] ?? [];
            $ordered = ['Property and loan summary' => $r['property_loan_summary'] ?? $r['scope'], 'Review results' => ['Market Rent DSCR' => $c['market_dscr']['display'] ?? 'UNKNOWN', 'Qualifying DSCR' => $c['qualifying_dscr']['display'] ?? 'UNKNOWN', 'LTV percent' => $c['ltv']['display_percent'] ?? 'UNKNOWN', 'Appraised value' => $c['appraised_value'] ?? 'UNKNOWN', 'Processing coverage status' => $r['processing_status'] ?? 'unknown', 'Processing limitation' => $r['processing_error'] ?? null, 'Status' => $c['status'] ?? 'No confirmed calculation', 'Assumptions and missing inputs' => $c['missing'] ?? []], 'Forms and coverage' => ['pinned_input_versions' => $r['input_versions'] ?? [], 'processed_documents' => $r['documents']], 'Rental and payment inputs' => $c['input'] ?? [], 'Calculation precision and policy' => array_diff_key($c, array_flip(['input', 'units'])), 'Items Requiring Attention' => $r['findings'], 'Guideline citations' => $r['checklist'], 'Appraisal facts' => $r['facts'], 'Source references' => $r['sources'], 'Reviewer notes and decisions' => ['notes' => $r['notes'], 'decisions' => $r['decisions']], 'Revision comparison' => $r['revisions'], 'Report versions' => array_intersect_key($r, array_flip(['report_version', 'run_id', 'hash', 'issued_at', 'actor', 'status']))];
            foreach ($ordered as $k => $v) {
                $html .= '<h2>'.htmlspecialchars(ucwords(str_replace('_', ' ', $k))).'</h2><table>';
                foreach ($this->flatten(is_array($v) ? $v : ['value' => $v]) as $row) {
                    $html .= '<tr><td>'.htmlspecialchars($row[0]).'</td><td>'.htmlspecialchars($row[1]).'</td></tr>';
                }$html .= '</table>';
            }$pdf->loadHtml($html.'</body></html>');
            $pdf->setPaper('letter');
            $pdf->render();

            return [$pdf->output(), 'application/pdf'];
        }
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $c = $r['calculation'] ?? [];
        $tabs = ['Summary' => array_diff_key($r, array_flip(['facts', 'leases', 'calculation', 'findings', 'sources', 'notes', 'decisions', 'revisions'])), 'Appraisal facts' => $r['facts'], 'Unit rents and leases' => ['units' => $c['input']['units'] ?? [], 'matches' => $r['leases']], 'Payment inputs' => $c['input']['payment'] ?? [], 'DSCR and LTV' => $c, 'Findings' => $r['findings'], 'Source references' => $r['sources'], 'Reviewer notes' => ['notes' => $r['notes'], 'decisions' => $r['decisions']], 'Revision summary' => $r['revisions']];
        foreach ($tabs as $name => $data) {
            $s = $book->createSheet();
            $s->setTitle($name);
            $s->fromArray([['Field / source', 'Value']], null, 'A1');
            $i = 2;
            foreach ($this->flatten($data) as $row) {
                $s->setCellValueExplicit('A'.$i, $row[0], DataType::TYPE_STRING);
                $s->setCellValueExplicit('B'.$i, $row[1], DataType::TYPE_STRING);
                $i++;
            }$s->getColumnDimension('A')->setWidth(52);
            $s->getColumnDimension('B')->setWidth(80);
            $s->getStyle('A1:B'.$i)->getAlignment()->setWrapText(true);
            $s->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_LETTER)->setFitToWidth(1)->setFitToHeight(0);
            $s->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
            $s->getPageSetup()->setPrintArea('A1:B'.$i);
            $s->freezePane('A2');
            $s->getStyle('A1:B1')->getFont()->setBold(true);
        }
        $s = $book->getSheetByName('DSCR and LTV');
        $i = $s->getHighestRow() + 3;
        foreach (['market_dscr', 'qualifying_dscr', 'without_adu_dscr', 'with_adu_dscr'] as $key) {
            if (! isset($c[$key])) {
                continue;
            }$s->setCellValue('A'.$i, $key.' checked backend formula');
            $s->setCellValue('B'.$i, '=ROUND('.$c[$key]['numerator_minor'].'/'.$c[$key]['denominator_minor'].',2)');
            $s->getStyle('B'.$i)->getNumberFormat()->setFormatCode('0.00');
            if (number_format((float) $s->getCell('B'.$i)->getCalculatedValue(), 2, '.', '') !== $c[$key]['display']) {
                throw new ProcessingException('Workbook DSCR formula mismatch');
            }$i++;
        }if (isset($c['ltv'])) {
            $s->setCellValue('A'.$i, 'LTV percent checked');
            $s->setCellValue('B'.$i, '=ROUND('.$c['ltv']['loan_minor'].'*100/'.$c['ltv']['value_basis_minor'].',2)');
            $s->getStyle('B'.$i)->getNumberFormat()->setFormatCode('0.00');
            if (number_format((float) $s->getCell('B'.$i)->getCalculatedValue(), 2, '.', '') !== $c['ltv']['display_percent']) {
                throw new ProcessingException('Workbook LTV mismatch');
            }
        }$s->getPageSetup()->setPrintArea('A1:B'.$s->getHighestRow());
        $book->setActiveSheetIndex(0);
        $w = new Xlsx($book);
        $w->setPreCalculateFormulas(true);
        ob_start();
        try {
            $w->save('php://output');

            return [ob_get_contents(), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        } finally {
            ob_end_clean();
            $book->disconnectWorksheets();
        }
    }
}
