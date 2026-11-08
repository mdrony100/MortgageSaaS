<?php

namespace App\Appraisal;

class Guidelines
{
    public static function baseline(): array
    {
        return ['name' => 'Fannie Mae public baseline — Madison/client review required', 'version' => 'draft-2026-11-08', 'status' => 'draft', 'retrieved_at' => '2026-11-08', 'rules' => [
            ['id' => 'forms-exhibits', 'section' => 'B4-1.2-01', 'url' => 'https://selling-guide.fanniemae.com/sel/b4-1.2-01/appraisal-report-forms-and-exhibits', 'source_effective_date' => '2025-09-03', 'applicability' => 'Applicable traditional residential appraisal; verify UAD version and exhibit path', 'evidence' => ['form/version', 'photographs', 'sketch/map', 'certification'], 'wording' => 'Verify Against Program Guidelines: review applicable form, exhibits and certification; visible signature is not authenticated.'],
            ['id' => 'rental-evidence', 'section' => 'B3-3.8-02', 'url' => 'https://selling-guide.fanniemae.com/sel/b3-3.8-02/rental-income-subject-property', 'source_effective_date' => '2026-09-02', 'applicability' => 'Subject rental-income evidence; purchase/refinance path must be confirmed', 'evidence' => ['lease/amendments', '1007/1025 as applicable', 'ownership/continuation evidence'], 'wording' => 'Verify Against Program Guidelines: confirm the applicable rental-documentation path. No DSCR/Non-QM disqualification is inferred.']], 'notice' => 'Minimal source-backed draft summaries, not a copied guide, approved policy or automatic underwriting.'];
    }
}
