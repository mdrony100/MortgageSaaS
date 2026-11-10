<?php

namespace App\Services;

class SampleData
{
    public static function pdf(): string
    {
        $text = 'BT /F1 14 Tf 50 740 Td (FICTIONAL SAMPLE - NOT A FINANCIAL DOCUMENT) Tj 0 -40 Td /F1 11 Tf (MortgageLens source evidence demonstration. Page 1.) Tj 0 -30 Td (Sample deposit: Client receipt 4500.00; Transfer 2500.00.) Tj 0 -30 Td (Sample property: 100 Example Lane. Not a real address.) Tj 0 -30 Td (Comparable 2 adjustment, rent schedule and image review pending.) Tj ET';
        $objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Length '.strlen($text)." >>\nstream\n".$text."\nendstream"];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n$obj\nendobj\n";
        } $start = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $o) {
            $pdf .= sprintf('%010d 00000 n ', $o)."\n";
        }

        return $pdf."trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$start\n%%EOF";
    }

    public static function all(int $source): array
    {
        $e = ['document_version_id' => $source, 'page' => 1, 'region' => ['x' => 40, 'y' => 80, 'width' => 520, 'height' => 180], 'excerpt' => 'Fictional source evidence; not extracted from uploaded documents.'];

        return ['label' => 'SAMPLE ANALYSIS — fictional fixtures, not lending advice', 'source' => $e,
            'bank' => ['overview' => ['account' => 'Fictional Operating ••0042', 'statement_months' => 12, 'account_type' => 'business', 'qualifying_income' => '7200.00', 'currency' => 'USD', 'coverage' => 'Sample 12 months; 24 month input available', 'request' => 'Business workflow: request two business bank statements for initial review.'],
                'transactions' => [['id' => 'tx-1', 'date' => '2026-01-05', 'description' => 'Fictional client receipt', 'amount' => '4500.00', 'classification' => 'eligible_deposit', 'confidence' => 'sample', 'source' => $e], ['id' => 'tx-2', 'date' => '2026-01-07', 'description' => 'Internal transfer from sample savings', 'amount' => '2500.00', 'classification' => 'excluded_transfer', 'confidence' => 'sample', 'source' => $e], ['id' => 'tx-3', 'date' => '2026-01-09', 'description' => 'NSF fee (not a separate NSF event)', 'amount' => '-35.00', 'classification' => 'bank_fee', 'confidence' => 'sample', 'source' => $e]],
                'income' => ['gross_monthly_deposits' => '12000.00', 'excluded_transfers' => '0.00', 'expense_ratio' => '40.00', 'qualifying_income' => '7200.00', 'method' => 'Fixture only — no income calculation performed', 'workbook' => '24 monthly rows; explicit 12/24 selector. Overflow deposits and exclusions get separate traceable sheets.'],
                'transfers' => [['id' => 'transfer-1', 'from' => 'Fictional savings ••0011', 'to' => 'Fictional operating ••0042', 'amount' => '2500.00', 'match' => 'suggested — sample only', 'source' => $e]],
                'nsf' => ['events' => 1, 'fees' => 2, 'overdraft_days' => 3, 'notice' => 'An event and its fee are distinct. Counting conventions require lender approval.'],
                'sustainability' => ['score' => 'Sample: review', 'deposit_trend' => '-8% fictional', 'concentration' => '42% fictional', 'reserve_months' => '2.4 fictional', 'notice' => 'No live sustainability model is configured.'],
                'integrity' => [['id' => 'bank-1', 'severity' => 'high', 'status' => 'open', 'category' => 'potential_issue', 'title' => 'Sample typeface inconsistency', 'observation' => 'Fictional indicator for a reviewer to check. Not a fraud verdict. Original bytes are preserved.', 'source' => $e], ['id' => 'bank-2', 'severity' => 'medium', 'status' => 'open', 'category' => 'integrity', 'title' => 'Sample running balance discrepancy', 'observation' => 'Demonstration reconciliation difference; needs human verification.', 'source' => $e]]],
            'appraisal' => ['overview' => ['form' => '1004', 'supported_forms' => ['1004', '1025', '1007', '216'], 'units' => 1, 'occupancy' => 'investment', 'value' => '325000.00', 'review_status' => 'Sample — unresolved findings do not block sample reports'],
                'property' => ['address' => '100 Example Lane (fictional)', 'units' => 1, 'occupancy' => 'investment', 'condition' => 'Sample C3 — unverified', 'form' => '1004'],
                'comparables' => [['name' => 'Comparable A (fictional)', 'price' => '318000.00', 'distance' => '0.6 mi', 'adjustment' => '4.2%'], ['name' => 'Comparable B (fictional)', 'price' => '344000.00', 'distance' => '1.2 mi', 'adjustment' => '18.5%']],
                'rent' => ['form' => '1007 / 216 separate from occupancy', 'market_rent' => '3300.00', 'lease_rent' => '3300.00', 'notice' => 'Sample only; no lease extraction'],
                'photos' => [['title' => 'Subject exterior', 'status' => 'No real photo review performed', 'observation' => 'Fictional condition discrepancy, source page 1', 'source' => $e]],
                'findings' => [['id' => 'appraisal-1', 'severity' => 'high', 'status' => 'open', 'category' => 'valuation', 'title' => 'Comparable 2 adjustment needs support', 'observation' => 'Sample 18.5% gross adjustment exceeds fictional draft threshold. Not an approved guideline.', 'source' => $e], ['id' => 'appraisal-2', 'severity' => 'medium', 'status' => 'open', 'category' => 'condition', 'title' => 'Photo narrative discrepancy', 'observation' => 'Fictional observation only. Human review is required.', 'source' => $e], ['id' => 'appraisal-3', 'severity' => 'medium', 'status' => 'open', 'category' => 'rental', 'title' => 'Rent schedule evidence missing', 'observation' => 'Sample missing 1007 / 216 support.', 'source' => $e]],
                'revisions' => [['version' => 1, 'status' => 'Original sample', 'change' => 'No actual appraisal comparison performed'], ['version' => 2, 'status' => 'Fictional revision example', 'change' => 'Comparable narrative amended in fixture only']]],
            'dscr' => ['overview' => ['notice' => 'Scenario examples only. Saved draft inputs do not trigger real DSCR / LTV calculations.'], 'scenarios' => [['name' => 'Sample A', 'rent' => '3300.00', 'pitia' => '3000.00', 'formula' => '3300 / 3000', 'dscr' => '1.10', 'status' => 'Fictional example'], ['name' => 'Sample B', 'rent' => '2850.00', 'pitia' => '3000.00', 'formula' => '2850 / 3000', 'dscr' => '0.95', 'status' => 'Fictional example']], 'leases' => [['tenant' => 'Fictional Tenant', 'monthly_rent' => '3300.00', 'status' => 'Sample — not lease extraction', 'source' => $e]], 'guidelines' => ['name' => 'Fictional draft DSCR policy', 'version' => 1, 'status' => 'Not investor-approved', 'notice' => 'No Fannie Mae or lender approval is claimed.']]];
    }
}
