<?php

namespace Tests\Support;

use App\Bank\ExtractionSchema;
use App\Bank\StatementExtractor;
use App\Models\Bank\Run;
use Dompdf\Dompdf;

class FixtureExtractor implements StatementExtractor
{
    public array $byVersion = [];

    public function extract(array $manifest, Run $r, int $version, string $directory): array
    {
        return $this->byVersion[$version];
    }
}
class BankFixtures
{
    public static function statement(string $month = '2025-01', string $account = '1111', string $designation = 'business', array $transactions = []): array
    {
        $start = $month.'-01';
        $end = (new \DateTimeImmutable($start))->format('Y-m-t');

        return ['document_type' => 'bank_statement', 'schema_version' => ExtractionSchema::VERSION, 'institution' => 'FICTIONAL Test Bank', 'holder' => 'Fictional Example LLC', 'masked_account' => '****'.$account, 'currency' => 'USD', 'designation' => $designation, 'designation_source' => 'Synthetic approved test fixture', 'period_start' => $start, 'period_end' => $end, 'opening_balance' => '100000.00', 'closing_balance' => '100000.00', 'reported_credits' => '20000.00', 'reported_debits' => '20000.00', 'reported_fees' => '0.00', 'fees_separate_from_debits' => false, 'page_coverage' => [1], 'complete' => true, 'transactions' => $transactions ?: [
            self::tx($month.'-02', 'Merchant sales', '16666.67', '0.00', '116666.67', 1, 'business_revenue'), self::tx($month.'-03', 'Loan proceeds', '3333.33', '0.00', '120000.00', 2, 'loan_proceeds'), self::tx($month.'-04', 'Operating expense', '0.00', '20000.00', '100000.00', 3, 'other_deposit')]];
    }

    public static function tx(string $date, string $description, ?string $credit, ?string $debit, ?string $balance, int $row = 1, string $category = 'unresolved', ?string $ref = null): array
    {
        return ['posting_date' => $date, 'transaction_date' => null, 'description' => $description, 'credit' => $credit, 'debit' => $debit, 'original_amount' => ($credit ?? '?').' / '.($debit ?? '?'), 'running_balance' => $balance, 'reference' => $ref, 'page' => 1, 'source_row' => $row, 'excerpt' => $date.' '.$description.' '.($credit ?? '?').' '.($debit ?? '?'), 'category_suggestion' => $category, 'suggestion_explanation' => 'TEST ADAPTER ONLY; not a live accuracy claim'];
    }

    public static function pdf(array $s): string
    {
        $lines = ['FICTIONAL BANK STATEMENT — APPROVED SYNTHETIC TEST ONLY'];
        foreach (['Institution' => 'institution', 'Holder' => 'holder', 'Account' => 'masked_account', 'Currency' => 'currency', 'Designation' => 'designation', 'Period start' => 'period_start', 'Period end' => 'period_end', 'Opening balance' => 'opening_balance', 'Closing balance' => 'closing_balance', 'Total credits' => 'reported_credits', 'Total debits' => 'reported_debits', 'Fees' => 'reported_fees'] as $label => $key) {
            $lines[] = $label.': '.$s[$key];
        }
        $lines[] = 'Fees separate: no';
        $lines[] = 'Date | Description | Credit | Debit | Balance | Reference';
        foreach ($s['transactions'] as $t) {
            $lines[] = implode(' | ', [$t['posting_date'], $t['description'], $t['credit'], $t['debit'], $t['running_balance'], $t['reference']]);
        }
        $pdf = new Dompdf;
        $pdf->loadHtml('<html><body><pre style="font-size:8px">'.htmlspecialchars(implode("\n", $lines), ENT_QUOTES).'</pre></body></html>');
        $pdf->setPaper('letter');
        $pdf->render();

        return $pdf->output();
    }
}
