<?php

namespace App\Bank;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RuleProfile
{
    public static function validate(array $p): array
    {
        return Validator::make($p, [
            'reconciliation_tolerance_minor' => 'sometimes|integer|min:0|max:100', 'tolerance_rationale' => 'required_with:reconciliation_tolerance_minor|nullable|string|max:1000', 'workflow' => ['required', Rule::in(['personal', 'business'])], 'eligible_categories' => 'required|array|min:1', 'eligible_categories.*' => [Rule::in(config('bank.categories'))],
            'expense_treatment' => ['required', Rule::in(['none', 'remaining_percent'])], 'remaining_percent' => 'nullable|decimal:0,4|min:0|max:100',
            'ownership_required' => 'required|boolean', 'fixed_ownership_percent' => 'nullable|decimal:0,4|min:0|max:100',
            'rounding' => 'required|in:HALF_UP', 'required_evidence' => 'required|array', 'allow_provisional' => 'required|boolean', 'approval_reference' => 'nullable|string|max:500',
            'require_external_integrity' => 'required|boolean', 'require_no_open_findings' => 'required|boolean',
            'nsf_counting_mode' => ['sometimes', Rule::in(['reference_per_day', 'transaction_row'])], 'nsf_convention' => 'nullable|string|max:500', 'nsf_review_event_threshold' => 'nullable|integer|min:0|max:999',
            'repeat_amount_count' => 'nullable|integer|min:2|max:1000', 'cash_pattern_threshold_minor' => 'nullable|integer|min:1', 'circular_window_days' => 'nullable|integer|min:0|max:30', 'liquidity_threshold_minor' => 'nullable|integer|min:0', 'approved_thresholds' => 'required|boolean',
        ])->validate();
    }

    public static function example(string $workflow = 'business'): array
    {
        return ['reconciliation_tolerance_minor' => 1, 'tolerance_rationale' => 'TEST EXAMPLE: permit one-cent statement/extraction rounding only; reviewer must confirm any policy', 'workflow' => $workflow, 'eligible_categories' => $workflow === 'business' ? ['business_revenue'] : ['payroll_income', 'owner_distribution'], 'expense_treatment' => $workflow === 'business' ? 'remaining_percent' : 'none', 'remaining_percent' => $workflow === 'business' ? '50.00' : null, 'ownership_required' => $workflow === 'business', 'fixed_ownership_percent' => null, 'rounding' => 'HALF_UP', 'required_evidence' => ['full_selected_months', 'verified_accounts', 'reviewed_transactions'], 'allow_provisional' => true, 'approval_reference' => null, 'require_external_integrity' => true, 'require_no_open_findings' => true, 'approved_thresholds' => false, 'nsf_counting_mode' => 'reference_per_day', 'nsf_convention' => 'Explicit non-fee events; fees never counted twice', 'nsf_review_event_threshold' => 1, 'liquidity_threshold_minor' => 100000];
    }
}
