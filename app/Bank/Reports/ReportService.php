<?php

namespace App\Bank\Reports;

use App\Bank\BankStatus;
use App\Bank\CalculateIncome;
use App\Bank\Ledger;
use App\Bank\Money;
use App\Bank\ReviewState;
use App\Models\Bank\Run;
use App\Models\LoanFile;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function issue(Run $run, int $calculationId, bool $final, int $reviewerId, string $publicExplanation = ''): array
    {
        $publicExplanation = Ledger::redact($publicExplanation);
        $row = Ledger::query('bank_calculations', $run)->find($calculationId);
        abort_unless($row, 404);
        $snapshot = Ledger::decode($row)['data'];
        $integrity = BankStatus::integrity($run);
        if ($final) {
            $errors = [];
            $policy = $snapshot['policy'];
            if ($snapshot['status'] !== 'reviewable' || $snapshot['rule_status'] !== 'approved' || empty($policy['approval_reference'])) {
                $errors[] = 'Approved policy and fully reviewable calculation required';
            }
            if ($integrity['internal'] !== 'Completed') {
                $errors[] = 'Internal scanning/extraction checks incomplete';
            }
            if (! BankStatus::freshness($run)) {
                $errors[] = 'New or changed documents require a new analysis; old reports remain unchanged';
            }
            if (($policy['require_external_integrity'] ?? true) && ! $integrity['external_complete']) {
                $errors[] = 'External integrity review incomplete';
            }
            if (($policy['require_no_open_findings'] ?? true) && array_filter(ReviewState::findings($run), fn ($f) => ! in_array($f['status'], ['resolved', 'rejected'], true))) {
                $errors[] = 'Configured finding completeness gate not met';
            }
            $fresh = app(CalculateIncome::class)->calculate($run, ['account_keys' => $snapshot['account_keys'], 'ownership_percent' => $snapshot['ownership_percent'], 'remaining_percent' => $snapshot['remaining_percent'], 'financial_input_review_id' => $snapshot['financial_input_review_id'] ?? null], false);
            if ($fresh['snapshot_hash'] !== $snapshot['snapshot_hash']) {
                $errors[] = 'Reviewer decisions changed; recalculate before finalizing';
            }
            if ($errors) {
                throw ValidationException::withMessages(['finalization' => $errors]);
            }
        }
        $f = LoanFile::findOrFail($run->loan_file_id);
        $conditions = BankStatus::conditions($f);
        $common = ['borrower' => $f->borrower->name, 'loan_number' => $f->reference, 'file_name' => $f->name, 'calculation_id' => $calculationId, 'snapshot_hash' => $snapshot['snapshot_hash'], 'status' => $final ? 'final' : 'preliminary', 'report_version' => Ledger::query('bank_reports', $run)->count() + 1, 'reviewer_id' => $reviewerId, 'issued_at' => now()->toIso8601String(), 'notice' => 'Income determination only; not mortgage approval or an automatic decline'];
        $internal = $common + ['audience' => 'internal', 'snapshot' => $snapshot, 'integrity' => $integrity, 'findings' => ReviewState::findings($run), 'review_history' => array_merge(Ledger::rows('bank_reviews', $run), Ledger::rows('bank_relationships', $run), Ledger::rows('bank_transfer_reviews', $run), Ledger::rows('bank_finding_reviews', $run)), 'public_conditions' => $conditions];
        $key = Ledger::hash([$calculationId, $final, $reviewerId, $publicExplanation, $conditions, $internal['findings']]);
        $reports = [Ledger::append('bank_reports', $run, 'internal:'.$key, $internal)];
        if ($final) {
            // Independent, deliberately constructed broker projection. Never copy internal findings, policies or risk metrics.
            $brokerCommon = array_diff_key($common, array_flip(['calculation_id', 'reviewer_id']));
            $brokerCommon['report_version'] = Ledger::query('bank_reports', $run)->where('data->audience', 'broker')->count() + 1;
            $public = $brokerCommon + ['audience' => 'broker', 'income_usd' => $snapshot['monthly_display'], 'currency' => 'USD', 'months' => $snapshot['months'], 'period' => [$snapshot['selected_months'][0], end($snapshot['selected_months'])], 'monthly' => array_map(fn ($m) => ['month' => $m['month'], 'eligible_deposits_usd' => Money::dollars($m['eligible_minor'])], $snapshot['monthly']), 'conditions' => $conditions, 'explanation' => $publicExplanation];
            $reports[] = Ledger::append('bank_reports', $run, 'broker:'.$key, $public);
        }

        return $reports;
    }
}
