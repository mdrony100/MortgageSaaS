<?php

namespace App\Bank\Reports;

use App\Bank\Money;
use App\Bank\ProcessingException as RuntimeException;
use Brick\Math\BigDecimal;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Export
{
    public function generate(array $report, string $format): array
    {
        return match ($format) {
            'xlsx' => [$this->xlsx($report), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],'csv' => [$this->csv($report), 'text/csv; charset=UTF-8'],'pdf' => [$this->pdf($report), 'application/pdf'],default => throw new RuntimeException('Unsupported export')
        };
    }

    public static function csvSafe(string $text): string
    {
        return preg_match('/^[\s\x00-\x1f\x{FEFF}\x{200B}]*[=+@-]/u', $text) ? "'".$text : $text;
    }

    private function detail(array $snapshot): array
    {
        $out = [];
        foreach (array_merge($snapshot['included'], $snapshot['excluded']) as $row) {
            $out[$row['transaction_id']] = $row;
        }

        return array_values($out);
    }

    public function csv(array $r): string
    {
        $h = fopen('php://temp', 'r+');
        if ($r['audience'] === 'broker') {
            fputcsv($h, ['Report', 'Status', 'Monthly qualifying income USD', 'Months'], ',', '"', '');
            fputcsv($h, [$r['report_version'], $r['status'], $r['income_usd'], $r['months']], ',', '"', '');
            fputcsv($h, ['Month', 'Eligible deposits USD'], ',', '"', '');
            foreach ($r['monthly'] as $m) {
                fputcsv($h, [$m['month'], $m['eligible_deposits_usd']], ',', '"', '');
            }
        } else {
            fputcsv($h, ['Transaction ID', 'Date', 'Description', 'Credit USD', 'Eligible USD', 'Excluded USD', 'Reason', 'Document version', 'Page', 'Snapshot'], ',', '"', '');
            foreach ($this->detail($r['snapshot']) as $t) {
                fputcsv($h, [$t['transaction_id'], $t['posting_date'], self::csvSafe($t['original_description']), Money::dollars($t['credit_minor']), Money::dollars($t['eligible_minor']), Money::dollars($t['excluded_minor']), self::csvSafe($t['reason']), $t['document_version_id'], $t['page'], $r['snapshot_hash']], ',', '"', '');
            }
        }
        rewind($h);
        $out = stream_get_contents($h);
        fclose($h);

        return $out;
    }

    public function xlsx(array $r): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Income summary');
        $str = function ($s, $cell, $value) {
            $s->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);
        };
        foreach (['A1' => 'MortgageLens FALLBACK — client template matching pending', 'A2' => 'Loan Number', 'B2' => $r['loan_number'], 'A3' => 'Borrower', 'B3' => $r['borrower'], 'A4' => 'Report status', 'B4' => $r['status'], 'A5' => 'Snapshot', 'B5' => $r['snapshot_hash']] as $cell => $v) {
            $str($sheet, $cell, $v);
        }
        if ($r['audience'] === 'broker') {
            $sheet->fromArray([['Months', $r['months']], ['Qualifying income USD', $r['income_usd']], ['Month', 'Eligible deposits USD']], null, 'A7');
            $i = 10;
            foreach ($r['monthly'] as $m) {
                $str($sheet, 'A'.$i, $m['month']);
                $sheet->setCellValue('B'.$i, $m['eligible_deposits_usd']);
                $i++;
            }
        } else {
            $s = $r['snapshot'];
            $sheet->fromArray([['Selected months', $s['months']], ['Ownership percent', $s['ownership_percent']], ['Remaining income percent', $s['remaining_percent']], ['Qualifying income USD', $s['monthly_display']], ['Rule version', $s['rule_version']]], null, 'A7');
            $sheet->setCellValue('A12', 'Expense percent (proportional profile only)');
            if (($s['policy']['expense_treatment'] ?? null) === 'remaining_percent') {
                $sheet->setCellValue('B12', '=100-B9');
            } else {
                $str($sheet, 'B12', 'Not applied');
            }
            $sheet->fromArray([['Month', 'Observed deposits USD', 'Exclusions USD', 'Eligible USD', 'Coverage complete']], null, 'A13');
            $i = 14;
            foreach ($s['monthly'] as $m) {
                $str($sheet, 'A'.$i, $m['month']);
                $sheet->setCellValue('B'.$i, Money::dollars($m['observed_deposits_minor']));
                $sheet->setCellValue('C'.$i, Money::dollars($m['excluded_minor']));
                $sheet->setCellValue('D'.$i, '=B'.$i.'-C'.$i);
                $str($sheet, 'E'.$i, $m['coverage_complete'] ? 'yes' : 'no');
                $i++;
            }
            $sheet->setCellValue('A'.$i, 'Selected period total');
            foreach (['B', 'C', 'D'] as $c) {
                $sheet->setCellValue($c.$i, '=ROUND(SUM('.$c.'14:'.$c.($i - 1).'),2)');
            }
            if ((string) BigDecimal::of(number_format((float) $sheet->getCell('D'.$i)->getCalculatedValue(), 2, '.', ''))->multipliedBy(100)->toBigInteger() !== (string) $s['eligible_minor']) {
                throw new RuntimeException('Workbook totals disagree with backend snapshot');
            }
            $formulaRow = $i + 2;
            $sheet->setCellValue('A'.$formulaRow, 'Checked monthly income — selected numerator / selected denominator');
            if ($s['monthly_display'] !== null) {
                $sheet->setCellValue('D'.$formulaRow, '=ROUND(D'.$i.'*B8/100*B9/100/B7,2)');
                if (number_format((float) $sheet->getCell('D'.$formulaRow)->getCalculatedValue(), 2, '.', '') !== $s['monthly_display']) {
                    throw new RuntimeException('Workbook denominator or rounding mismatch');
                }
            } else {
                $str($sheet, 'D'.$formulaRow, 'WITHHELD — missing policy/evidence');
            }
            $sheet->getStyle('B14:D'.$i)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('D'.$formulaRow)->getNumberFormat()->setFormatCode('#,##0.00');
            $detail = $book->createSheet();
            $detail->setTitle('Transactions and exclusions');
            $detail->fromArray([['ID', 'Date', 'Original description', 'Credit USD', 'Eligible USD', 'Excluded USD', 'Reason', 'Document version', 'Page', 'Override ID']], null, 'A1');
            $j = 2;
            foreach ($this->detail($s) as $t) {
                $detail->fromArray([[$t['transaction_id'], $t['posting_date'], '', Money::dollars($t['credit_minor']), Money::dollars($t['eligible_minor']), Money::dollars($t['excluded_minor']), '', $t['document_version_id'], $t['page'], implode(',', $t['override_ids'] ?? [$t['override_id']])]], null, 'A'.$j);
                $str($detail, 'C'.$j, $t['original_description']);
                $str($detail, 'G'.$j, $t['reason']);
                $j++;
            }
            $accountSheet = $book->createSheet();
            $accountSheet->setTitle('Accounts and evidence');
            $accountSheet->fromArray([['Institution', 'Holder', 'Masked account', 'Designation', 'Verified', 'Selected income account']], null, 'A1');
            $row = 2;
            foreach ($s['account_reviews'] as $a) {
                foreach (['institution', 'holder', 'masked_account', 'designation'] as $index => $key) {
                    $str($accountSheet, chr(65 + $index).$row, $a[$key]);
                }
                $str($accountSheet, 'E'.$row, $a['verified'] ? 'yes' : 'no');
                $str($accountSheet, 'F'.$row, in_array($a['account_key'], $s['account_keys'], true) ? 'yes' : 'supporting only');
                $row++;
            }
            $nsf = $book->createSheet();
            $nsf->setTitle('NSF and coverage');
            $str($nsf, 'A1', $s['nsf']['negative_days_status']);
            $str($nsf, 'A2', $s['nsf']['counting_convention']);
            $nsf->fromArray([['Month', 'NSF events', 'OD events', 'NSF fees USD', 'OD fees USD']], null, 'A4');
            $j = 5;
            foreach ($s['nsf']['monthly'] as $month => $m) {
                $nsf->fromArray([[$month, $m['nsf_events'], $m['overdraft_events'], Money::dollars($m['nsf_fee_minor']), Money::dollars($m['overdraft_fee_minor'])]], null, 'A'.$j++);
            }
        }
        $conditions = $book->createSheet();
        $conditions->setTitle('Conditions');
        $str($conditions, 'A1', 'Deliberately public conditions');
        $j = 2;
        foreach ($r['conditions'] ?? $r['public_conditions'] ?? [] as $c) {
            $str($conditions, 'A'.$j++, $c['text']);
        }
        foreach ($book->getAllSheets() as $s) {
            $s->freezePane('A2');
            $s->getStyle('A1:J1')->getFont()->setBold(true);
            foreach (range('A', 'J') as $c) {
                $s->getColumnDimension($c)->setWidth($c === 'C' ? 45 : 24);
            }
        }
        $book->setActiveSheetIndex(0);
        $writer = new Xlsx($book);
        $writer->setPreCalculateFormulas(true);
        ob_start();
        try {
            $writer->save('php://output');

            return ob_get_contents();
        } finally {
            ob_end_clean();
            $book->disconnectWorksheets();
        }
    }

    public function pdf(array $r): string
    {
        $opts = new Options;
        $opts->set('isRemoteEnabled', false);
        $opts->set('isPhpEnabled', false);
        $opts->set('isJavascriptEnabled', false);
        $opts->set('chroot', resource_path('views/bank'));
        $pdf = new Dompdf($opts);
        $pdf->loadHtml(view($r['audience'] === 'broker' ? 'bank.broker' : 'bank.internal', ['report' => $r])->render());
        $pdf->setPaper('letter');
        $pdf->render();

        return $pdf->output();
    }
}
