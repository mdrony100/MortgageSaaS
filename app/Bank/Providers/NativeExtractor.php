<?php

namespace App\Bank\Providers;

use App\Bank\ExtractionSchema;
use App\Bank\StatementExtractor;
use App\Models\Bank\Run;

class NativeExtractor implements StatementExtractor
{
    public function extract(array $manifest, Run $run, int $versionId, string $directory): array
    {
        // Conservative supported native layout: explicit labels and pipe-delimited columns.
        // Other layouts retain native text/page provenance but require vision or manual source review.
        $text = implode("\n", array_column($manifest['pages'], 'text'));
        $label = function (string $key) use ($text): ?string {
            return preg_match('/^'.preg_quote($key, '/').':\s*(.*?)\s*$/mi', $text, $m) ? trim($m[1]) : null;
        };
        $d = [];
        foreach (ExtractionSchema::jsonSchema()['properties'] as $k => $v) {
            $d[$k] = null;
        }
        $d['schema_version'] = ExtractionSchema::VERSION;
        $d['document_type'] = preg_match('/bank statement/i', $text) ? 'bank_statement' : 'unresolved';
        foreach (['institution' => 'Institution', 'holder' => 'Holder', 'masked_account' => 'Account', 'currency' => 'Currency', 'designation' => 'Designation', 'period_start' => 'Period start', 'period_end' => 'Period end', 'opening_balance' => 'Opening balance', 'closing_balance' => 'Closing balance', 'reported_credits' => 'Total credits', 'reported_debits' => 'Total debits', 'reported_fees' => 'Fees'] as $k => $l) {
            $d[$k] = $label($l);
        }
        $d['designation_source'] = $d['designation'] ? 'Explicit statement label' : null;
        $d['fees_separate_from_debits'] = match ($label('Fees separate')) {
            'yes' => true,'no' => false,default => null
        };
        $d['page_coverage'] = array_column($manifest['pages'], 'number');
        $d['transactions'] = [];
        $header = false;
        foreach ($manifest['pages'] as $p) {
            foreach (explode("\n", $p['text']) as $i => $line) {
                if (trim($line) === 'Date | Description | Credit | Debit | Balance | Reference') {
                    $header = true;

                    continue;
                }
                if (! preg_match('/^(\d{4}-\d{2}-\d{2})\s*\|\s*(.*?)\s*\|\s*([\d.]*|null)\s*\|\s*([\d.]*|null)\s*\|\s*(-?[\d.]*|null)\s*\|\s*(.*?)\s*$/', $line, $m)) {
                    continue;
                }
                $nullable = fn ($s) => $s === '' || $s === 'null' ? null : $s;
                $d['transactions'][] = ['posting_date' => $m[1], 'transaction_date' => null, 'description' => $m[2], 'credit' => $nullable($m[3]), 'debit' => $nullable($m[4]), 'running_balance' => $nullable($m[5]), 'reference' => $nullable($m[6]), 'original_amount' => $m[3].' | '.$m[4], 'page' => $p['number'], 'source_row' => $i + 1, 'excerpt' => $line, 'category_suggestion' => 'unresolved', 'suggestion_explanation' => 'Native extraction; reviewer classification required'];
            }
        }
        $d['complete'] = $d['document_type'] === 'bank_statement' && $header && $d['period_start'] !== null && $d['period_end'] !== null && $d['opening_balance'] !== null && $d['closing_balance'] !== null && ! array_filter($manifest['pages'], fn ($p) => trim($p['text']) === '');

        return ExtractionSchema::validate($d, $d['page_coverage']);
    }
}
