<?php

namespace App\Bank;

use App\Models\Bank\Run;
use Illuminate\Support\Facades\DB;

class CalculateIncome
{
    public function calculate(Run $run, array $choices = [], bool $persist = true): array
    {
        $options = array_replace($run->options, $choices);
        $rule = $run->rule_id ? DB::table('bank_rules')->where('id', $run->rule_id)->where('organization_id', $run->organization_id)->first() : null;
        $policy = $rule ? json_decode($rule->policy, true) : [];
        $workflow = $options['workflow'];
        $months = (int) $options['months'];
        if ($persist) {
            app(FindingRefresh::class)->internal($run, $policy);
        }
        $end = \DateTimeImmutable::createFromFormat('!Y-m', $options['end_month']);
        $selectedMonths = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $selectedMonths[] = $end->modify('-'.$i.' months')->format('Y-m');
        }
        $accounts = ReviewState::accounts($run);
        $tx = ReviewState::transactions($run);
        $statements = ReviewState::statements($run);
        $transfers = ReviewState::transfers($run);
        foreach ($options['preview_transactions'] ?? [] as $preview) {
            foreach ($tx as &$entry) {
                if ($entry['id'] === $preview['id']) {
                    $entry['current_category'] = $preview['category'];
                    $entry['include'] = $preview['include'] ?? null;
                    $entry['review_id'] = 'preview';
                }
            }
        } unset($entry);
        $selected = $options['account_keys'] ?? [];
        $missing = [];
        $warnings = [];
        $assumptions = [];
        if (! $selected) {
            $missing[] = 'Explicitly select income accounts';
        }
        if (! $rule) {
            $missing[] = 'Select a versioned rule profile before starting analysis';
        }
        if ($policy && ($policy['workflow'] ?? null) !== $workflow) {
            $missing[] = 'Rule profile does not apply to selected workflow';
        }
        $own = $options['ownership_percent'] ?? $policy['fixed_ownership_percent'] ?? null;
        if ($workflow === 'personal' && ! ($policy['ownership_required'] ?? false)) {
            $own = '100';
        }
        if (($policy['ownership_required'] ?? true) && $own === null) {
            $missing[] = 'Reviewer-confirmed ownership percentage';
        }
        $remaining = ($policy['expense_treatment'] ?? null) === 'none' ? '100' : ($options['remaining_percent'] ?? $policy['remaining_percent'] ?? null);
        if ($remaining === null) {
            $missing[] = 'Confirmed expense treatment / remaining-income percentage';
        }
        if ($rule && $rule->status !== 'approved') {
            $warnings[] = 'Unconfirmed draft/example policy; not a lender policy';
        }
        $coverage = [];
        foreach ($selected as $key) {
            if (! isset($accounts[$key]) || $accounts[$key]['designation'] !== $workflow) {
                $missing[] = 'Account selection conflicts with workflow';

                continue;
            }
            if (! $accounts[$key]['verified']) {
                $warnings[] = 'Account identity/designation requires reviewer verification: '.$accounts[$key]['masked_account'];
            }
            $sp = array_values(array_filter($statements, fn ($s) => $s['data']['account_key'] === $key));
            foreach ($selectedMonths as $month) {
                $start = $month.'-01';
                $last = (new \DateTimeImmutable($start))->format('Y-m-t');
                $intervals = [];
                foreach ($sp as $s) {
                    $d = $s['data'];
                    if (($d['currency'] ?? null) !== 'USD') {
                        $missing[] = 'Selected account currency unsupported or unknown';
                    }
                    if (! ExtractionSchema::date($d['period_start']) || ! ExtractionSchema::date($d['period_end'])) {
                        continue;
                    }
                    if ($d['period_end'] >= $start && $d['period_start'] <= $last) {
                        $intervals[] = [max($start, $d['period_start']), min($last, $d['period_end']), $d['document_version_id']];
                        if (! $d['complete']) {
                            $warnings[] = 'Incomplete extraction for document '.$d['document_version_id'];
                        }
                    }
                }
                usort($intervals, fn ($a, $b) => strcmp($a[0], $b[0]));
                $cursor = $start;
                $overlap = false;
                foreach ($intervals as [$a,$b,$vid]) {
                    if ($a < $cursor) {
                        $overlap = true;
                    } if ($a > $cursor) {
                        break;
                    } $cursor = max($cursor, (new \DateTimeImmutable($b))->modify('+1 day')->format('Y-m-d'));
                }
                $full = $cursor > $last;
                $coverage[] = ['account_key' => $key, 'month' => $month, 'complete' => $full, 'overlap' => $overlap, 'document_version_ids' => array_column($intervals, 2)];
                if (! $full) {
                    $warnings[] = 'Missing coverage: '.$month.' '.$accounts[$key]['masked_account'];
                }
                if ($overlap) {
                    $missing[] = 'Overlapping/duplicate coverage: '.$month.' '.$accounts[$key]['masked_account'];
                }
            }
        }
        $reconciliation = [];
        foreach ($statements as $st) {
            $entries = array_values(array_filter($tx, fn ($t) => $t['statement_id'] === $st['id']));
            $issues = Reconcile::statement($st['data'], $entries, (int) ($policy['reconciliation_tolerance_minor'] ?? 1));
            $reconciliation[$st['id']] = $issues;
            if ($issues && in_array($st['data']['account_key'], $selected, true)) {
                $warnings[] = 'Reconciliation incomplete/inconsistent for statement '.$st['id'];
            }
        }
        $byId = array_column($tx, null, 'id');
        $allocations = [];
        foreach ($transfers as $m) {
            if (in_array($m['status'], ['proposed', 'ambiguous', 'relationship_unverified'], true) && in_array($byId[$m['data']['source_id']]['account_key'], $selected, true) && in_array($byId[$m['data']['destination_id']]['account_key'], $selected, true)) {
                $missing[] = 'Resolve selected-account transfer candidate '.$m['id'].' before income can be determined';
            }
        }
        foreach ($transfers as $m) {
            if ($m['status'] !== 'confirmed') {
                continue;
            }
            $from = $byId[$m['data']['source_id']];
            $to = $byId[$m['data']['destination_id']];
            // Business → personal source corroboration is NOT a second selected-income account or automatic exclusion.
            if (in_array($from['account_key'], $selected, true) && in_array($to['account_key'], $selected, true)) {
                $allocations[$to['id']] = ($allocations[$to['id']] ?? 0) + $m['allocated_minor'];
            }
        }
        $monthly = [];
        foreach ($selectedMonths as $m) {
            $monthly[$m] = ['month' => $m, 'observed_deposits_minor' => 0, 'excluded_minor' => 0, 'eligible_minor' => 0, 'transaction_ids' => [], 'coverage_complete' => ! in_array(false, array_column(array_filter($coverage, fn ($c) => $c['month'] === $m), 'complete'), true)];
        }
        $included = [];
        $excluded = [];
        $corroboration = [];
        $eligible = 0;
        $deposit = 0;
        foreach ($tx as $t) {
            if (! in_array($t['account_key'], $selected, true)) {
                continue;
            }
            $month = $t['posting_date'] ? substr($t['posting_date'], 0, 7) : null;
            if (! $month) {
                $warnings[] = 'Unreadable/invalid transaction date: '.$t['id'];

                continue;
            }
            if (! isset($monthly[$month])) {
                continue;
            }
            $sourceStatement = array_values(array_filter($statements, fn ($s) => $s['id'] === $t['statement_id']))[0]['data'];
            if (! $sourceStatement['period_start'] || ! $sourceStatement['period_end'] || $t['posting_date'] < $sourceStatement['period_start'] || $t['posting_date'] > $sourceStatement['period_end']) {
                $warnings[] = 'Out-of-statement transaction withheld: '.$t['id'];

                continue;
            }
            if ($t['credit_minor'] === null && $t['debit_minor'] === null) {
                $warnings[] = 'Unreadable amount: '.$t['id'];

                continue;
            }
            $amount = $t['credit_minor'] ?? 0;
            if ($amount <= 0) {
                continue;
            }
            $deposit += $amount;
            $monthly[$month]['observed_deposits_minor'] += $amount;
            $monthly[$month]['transaction_ids'][] = $t['id'];
            $category = $t['current_category'];
            $isEligible = $t['include'] !== null ? $t['include'] : in_array($category, $policy['eligible_categories'] ?? [], true);
            if ($category === 'unresolved') {
                $isEligible = false;
                $warnings[] = 'Unresolved classification: '.$t['id'];
            }
            if (! $t['review_id']) {
                $warnings[] = 'Unreviewed classification: '.$t['id'];
            }
            $transfer = min($amount, $allocations[$t['id']] ?? 0);
            $counted = $isEligible ? max(0, $amount - $transfer) : 0;
            $detail = ['transaction_id' => $t['id'], 'statement_id' => $t['statement_id'], 'document_version_id' => $t['document_version_id'], 'account_key' => $t['account_key'], 'posting_date' => $t['posting_date'], 'original_description' => $t['data']['description'], 'page' => $t['data']['page'], 'category' => $category, 'credit_minor' => $amount, 'eligible_minor' => $counted, 'excluded_minor' => $amount - $counted, 'reason' => $transfer ? 'Confirmed selected-account transfer allocation' : ($isEligible ? 'Eligible per profile/reviewer' : 'Category ineligible, unresolved or reviewer excluded'), 'override_id' => $t['review_id'], 'override_ids' => $t['applied_review_ids'] ?? []];
            if ($counted) {
                $included[] = $detail;
            }
            if ($amount - $counted) {
                $excluded[] = $detail;
            }
            $eligible += $counted;
            $monthly[$month]['eligible_minor'] += $counted;
            $monthly[$month]['excluded_minor'] += $amount - $counted;
            $businessSource = in_array($category, ['owner_distribution', 'business_revenue'], true);
            foreach ($transfers as $match) {
                if ($match['status'] === 'confirmed' && $match['data']['destination_id'] === $t['id'] && ($accounts[$byId[$match['data']['source_id']]['account_key']]['designation'] ?? null) === 'business') {
                    $businessSource = true;
                }
            }
            if ($workflow === 'personal' && $businessSource) {
                $cor = $this->corroborate($t, $tx, $statements, $accounts, $transfers, $policy);
                $corroboration[] = $cor;
                if ($cor['status'] !== 'supported') {
                    $warnings[] = 'Business source '.$cor['status'].' for personal deposit '.$t['id'];
                }
            }
        }
        foreach (ReviewState::findings($run) as $finding) {
            if (($finding['data']['code'] ?? null) === 'duplicate_page' && ! in_array($finding['status'], ['resolved', 'rejected'], true)) {
                $missing[] = 'Resolve duplicate page evidence before calculating income';
            }
        }
        foreach (ReviewState::findings($run) as $finding) {
            if (! in_array($finding['status'], ['resolved', 'rejected'], true) && in_array($finding['data']['category'], ['financial_inconsistency', 'extraction_uncertainty', 'missing_evidence'], true)) {
                $warnings[] = 'Unresolved evidence finding '.$finding['id'];
            }
        }
        $missing = array_values(array_unique($missing));
        $warnings = array_values(array_unique($warnings));
        if ($warnings) {
            $assumptions[] = 'Observed-only selected-period numerator; denominator remains exactly '.$months.' months. Missing months/unknown amounts are NOT asserted to be zero or verified. This is not a final qualifying figure.';
        }
        $allowed = $policy['allow_provisional'] ?? false;
        $status = $missing || ($warnings && ! $allowed) ? 'withheld' : ($warnings ? 'provisional' : 'reviewable');
        $math = $status === 'withheld' ? ['monthly_exact' => null, 'monthly_display' => null, 'adjusted_minor_exact' => null, 'rounding' => 'HALF_UP at display (2 decimal USD)'] : Money::monthly($eligible, (string) $own, (string) $remaining, $months);
        $metricAccounts = $selected ?: array_keys($accounts);
        $metricStatements = array_values(array_filter($statements, fn ($s) => in_array($s['data']['account_key'], $metricAccounts, true) && ($s['data']['period_end'] ?? '') >= $selectedMonths[0].'-01' && ($s['data']['period_start'] ?? '') <= $end->format('Y-m-t')));
        $metricIds = array_column($metricStatements, 'id');
        $metricTx = array_values(array_filter($tx, fn ($t) => in_array($t['statement_id'], $metricIds, true)));
        $supportStatements = array_values(array_filter($statements, fn ($s) => ! in_array($s['data']['account_key'], $metricAccounts, true)));
        $supportIds = array_column($supportStatements, 'id');
        $supportTx = array_values(array_filter($tx, fn ($t) => in_array($t['statement_id'], $supportIds, true)));
        $data = ['schema' => 'income-snapshot-v1', 'workflow' => $workflow, 'financial_input_review_id' => $options['financial_input_review_id'] ?? null, 'rule_id' => $run->rule_id, 'rule_name' => $rule->name ?? null, 'rule_version' => $rule->version ?? null, 'rule_status' => $rule->status ?? 'missing', 'policy' => $policy, 'months' => $months, 'selected_months' => $selectedMonths, 'account_keys' => $selected, 'ownership_percent' => $own, 'remaining_percent' => $remaining, 'observed_deposits_minor' => $deposit, 'excluded_minor' => $deposit - $eligible, 'eligible_minor' => $eligible, 'included' => $included, 'excluded' => $excluded, 'monthly' => array_values($monthly), 'coverage' => $coverage, 'corroboration' => $corroboration, 'missing_inputs' => $missing, 'warnings' => $warnings, 'assumptions' => $assumptions, 'status' => $status, 'inputs' => Ledger::rows('bank_inputs', $run), 'reconciliation' => $reconciliation, 'statement_review_ids' => array_values(array_map(fn ($x) => $x['id'], array_filter(Ledger::rows('bank_reviews', $run), fn ($x) => $x['data']['kind'] === 'statement'))), 'account_reviews' => array_values($accounts), 'transfer_review_ids' => array_values(array_filter(array_column($transfers, 'review_id'))), 'override_ids' => array_values(array_unique(array_merge(array_merge(...array_column($tx, 'applied_review_ids')), isset($options['financial_input_review_id']) ? [$options['financial_input_review_id']] : []))), 'nsf' => RiskMetrics::nsf($metricTx, $metricStatements, $policy, ['start' => $selectedMonths[0].'-01', 'end' => $end->format('Y-m-t')]), 'supporting_account_nsf' => RiskMetrics::nsf($supportTx, $supportStatements, $policy), 'sustainability' => RiskMetrics::sustainability($tx, $statements, $accounts, $policy, $transfers)] + $math;
        $data['snapshot_hash'] = Ledger::hash($data);
        if (! $persist) {
            return $data;
        }
        app(FindingRefresh::class)->metrics($run, $data);

        return Ledger::append('bank_calculations', $run, $data['snapshot_hash'], $data);
    }

    private function corroborate(array $personal, array $tx, array $statements, array $accounts, array $matches, array $policy): array
    {
        $candidates = [];
        foreach ($accounts as $a) {
            if ($a['designation'] === 'business' && $a['verified']) {
                $s = array_values(array_filter($statements, fn ($s) => $s['data']['account_key'] === $a['account_key']));
                usort($s, fn ($a, $b) => strcmp($b['data']['period_end'] ?? '', $a['data']['period_end'] ?? ''));
                $latest = array_slice($s, 0, 2);
                $coverage = array_map(fn ($s) => ['start' => $s['data']['period_start'], 'end' => $s['data']['period_end'], 'complete' => $s['data']['complete'], 'document_version_id' => $s['data']['document_version_id']], $latest);
                $inWindow = false;
                foreach ($coverage as $c) {
                    if ($c['start'] && $c['end'] && $personal['posting_date'] >= $c['start'] && $personal['posting_date'] <= $c['end']) {
                        $inWindow = true;
                    }
                }
                $supported = 0;
                $evidence = [];
                foreach ($matches as $m) {
                    if ($m['status'] === 'confirmed' && $m['data']['destination_id'] === $personal['id']) {
                        $source = array_values(array_filter($tx, fn ($t) => $t['id'] === $m['data']['source_id']))[0] ?? null;
                        if ($source && $source['account_key'] === $a['account_key'] && in_array($source['document_version_id'], array_column($coverage, 'document_version_id'), true)) {
                            $supported += $m['allocated_minor'];
                            $evidence[] = $m['id'];
                        }
                    }
                }
                $two = count($latest) === 2 && ExtractionSchema::date($latest[0]['data']['period_start']) && ExtractionSchema::date($latest[1]['data']['period_end']) && (new \DateTimeImmutable($latest[1]['data']['period_end']))->modify('+1 day')->format('Y-m-d') === $latest[0]['data']['period_start'] && count(array_unique(array_map(fn ($s) => substr($s['data']['period_end'] ?? '', 0, 7), $latest))) === 2 && ! in_array(false, array_column($coverage, 'complete'), true);
                if ($two) {
                    $start = $latest[1]['data']['period_start'];
                    $end = $latest[0]['data']['period_end'];
                    $two = $start && $end && (new \DateTimeImmutable($start))->modify('+2 months') <= (new \DateTimeImmutable($end))->modify('+1 day') && ! Reconcile::continuity($latest, (int) ($policy['reconciliation_tolerance_minor'] ?? 1));
                }
                foreach ($latest as $business) {
                    if (($business['data']['currency'] ?? null) !== 'USD' || Reconcile::statement($business['data'], array_values(array_filter($tx, fn ($t) => $t['statement_id'] === $business['id'])), (int) ($policy['reconciliation_tolerance_minor'] ?? 1))) {
                        $two = false;
                    }
                }
                $candidates[] = ['account_key' => $a['account_key'], 'coverage' => $coverage, 'two_recent_statements' => $two, 'in_window' => $inWindow, 'supported_minor' => $supported, 'match_ids' => $evidence];
            }
        }
        $supported = false;
        $inWindow = false;
        foreach ($candidates as $c) {
            $inWindow = $inWindow || $c['in_window'];
            $supported = $supported || ($c['two_recent_statements'] && $c['in_window'] && $c['supported_minor'] >= ($personal['credit_minor'] ?? PHP_INT_MAX));
        }

        return ['personal_transaction_id' => $personal['id'], 'status' => $supported ? 'supported' : ($candidates && ! $inWindow ? 'outside_window' : 'unsupported'), 'business_evidence' => $candidates, 'notice' => 'Corroboration only for actual business statement windows, never all 12/24 months. Business deposits are not added to personal income.'];
    }
}
