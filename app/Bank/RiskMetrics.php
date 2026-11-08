<?php

namespace App\Bank;

class RiskMetrics
{
    public static function nsf(array $tx, array $statements, array $policy = [], array $scope = []): array
    {
        $monthly = [];
        $eventKeys = [];
        $lowest = null;
        $sources = [];
        $unsupported = [];
        $currencies = [];
        $usdStatements = [];
        foreach ($statements as $s) {
            $currency = $s['data']['currency'] ?? null;
            if (isset($s['id'])) {
                $currencies[$s['id']] = $currency;
            }
            if ($currency === 'USD') {
                $usdStatements[] = $s;
                foreach (['opening_balance', 'closing_balance'] as $k) {
                    $observedDate = $s['data'][$k === 'opening_balance' ? 'period_start' : 'period_end'] ?? null;
                    if ($observedDate && ((isset($scope['start']) && $observedDate < $scope['start']) || (isset($scope['end']) && $observedDate > $scope['end']))) {
                        continue;
                    }
                    $value = Money::minor($s['data'][$k] ?? null);
                    if ($value !== null) {
                        $lowest = $lowest === null ? $value : min($lowest, $value);
                    }
                }
            } else {
                $unsupported[] = $s['data']['document_version_id'];
            }
        }
        $usdTx = array_values(array_filter($tx, fn ($t) => isset($t['statement_id']) ? ($currencies[$t['statement_id']] ?? null) === 'USD' : ! $unsupported));
        $mode = $policy['nsf_counting_mode'] ?? 'reference_per_day';
        foreach ($usdTx as $t) {
            if ($t['posting_date'] && ((isset($scope['start']) && $t['posting_date'] < $scope['start']) || (isset($scope['end']) && $t['posting_date'] > $scope['end']))) {
                continue;
            }
            if ($t['balance_minor'] !== null) {
                $lowest = $lowest === null ? $t['balance_minor'] : min($lowest, $t['balance_minor']);
            }
            $desc = strtolower($t['data']['description']);
            $kind = preg_match('/\bnsf\b|insufficient funds|returned item/', $desc) ? 'nsf' : (preg_match('/overdraft|\bod\b/', $desc) ? 'overdraft' : null);
            if (! $kind || ! $t['posting_date']) {
                continue;
            }
            $month = substr($t['posting_date'], 0, 7);
            $monthly[$month] ??= ['nsf_events' => 0, 'overdraft_events' => 0, 'nsf_fee_minor' => 0, 'overdraft_fee_minor' => 0, 'evidence' => []];
            $fee = (bool) preg_match('/fee|charge/', $desc);
            $ref = $t['data']['reference'] ?? null;
            $key = $t['account_key'].':'.$t['posting_date'].':'.$kind.':'.($mode === 'transaction_row' ? $t['id'] : ($ref ?: $t['id']));
            if ($fee) {
                $monthly[$month][$kind.'_fee_minor'] += $t['debit_minor'] ?? 0;
            } else {
                if (! isset($eventKeys[$key])) {
                    $monthly[$month][$kind.'_events']++;
                    $eventKeys[$key] = true;
                }
            }
            $monthly[$month]['evidence'][] = ['transaction_id' => $t['id'], 'type' => $kind, 'fee' => $fee, 'event_reference' => $ref];
            $sources[] = $t['id'];
        }
        // A fee is not automatically another event. Fee-only events remain unknown, not inferred.
        $counts = ['nsf_events' => 0, 'overdraft_events' => 0, 'nsf_fee_minor' => 0, 'overdraft_fee_minor' => 0];
        foreach ($monthly as $m) {
            foreach ($counts as $k => $v) {
                $counts[$k] += $m[$k];
            }
        }
        $negative = self::negativeDays($usdTx, $usdStatements, $policy, $scope);
        $threshold = $policy['nsf_review_event_threshold'] ?? null;
        $conditions = $threshold !== null && ($counts['nsf_events'] + $counts['overdraft_events']) >= $threshold ? [['rule' => 'nsf-threshold-v1', 'observation' => 'Observed NSF/OD event count meets configured review threshold; request explanation, never decline', 'threshold' => $threshold, 'threshold_status' => ($policy['approved_thresholds'] ?? false) ? 'approved' : 'example-only', 'source_transaction_ids' => $sources]] : [];

        return ['review_conditions' => $conditions, 'rule' => 'nsf-v1', 'scope' => $scope, 'currency' => 'USD', 'unsupported_currency_document_ids' => array_values(array_unique($unsupported)), 'currency_notice' => 'Only USD monetary metrics aggregated; unsupported currencies are flagged, never converted or combined', 'counting_mode' => $mode, 'counting_convention' => $mode === 'transaction_row' ? 'Explicit non-fee source rows; fee rows are fees only.' : 'Explicit non-fee events grouped by account/date/reference; missing references retain separate source rows. Fees do not add events.', 'convention_note' => $policy['nsf_convention'] ?? null, 'monthly' => $monthly, 'cumulative' => $counts, 'lowest_observed_balance_minor' => $lowest, 'negative_balance_days' => $negative['days'], 'negative_days_status' => $negative['status'], 'negative_days_coverage' => $negative['coverage'], 'observed_vs_derived' => 'Events/fees observed; monthly/cumulative sums derived', 'coverage' => array_map(fn ($s) => ['document_version_id' => $s['data']['document_version_id'], 'start' => $s['data']['period_start'], 'end' => $s['data']['period_end']], $statements), 'source_transaction_ids' => $sources, 'action' => 'Review conditions only; no automatic decline'];
    }

    private static function negativeDays(array $tx, array $statements, array $policy, array $scope = []): array
    {
        $coverage = [];
        $negative = [];
        $complete = true;
        foreach ($statements as $row) {
            $s = $row['data'];
            $entries = array_values(array_filter($tx, fn ($t) => ($t['statement_id'] ?? null) === ($row['id'] ?? null)));
            if (! ($s['daily_reconstruction_verified'] ?? false) || ! $s['complete'] || ! ExtractionSchema::date($s['period_start']) || ! ExtractionSchema::date($s['period_end']) || $s['opening_balance'] === null || Reconcile::statement($s, $entries, 0)) {
                $complete = false;

                continue;
            }
            $balance = Money::minor($s['opening_balance']);
            $byDate = [];
            foreach ($entries as $t) {
                $byDate[$t['posting_date']][] = $t;
            }
            for ($date = new \DateTimeImmutable($s['period_start']); $date->format('Y-m-d') <= $s['period_end']; $date = $date->modify('+1 day')) {
                $day = $date->format('Y-m-d');
                foreach ($byDate[$day] ?? [] as $t) {
                    $balance += ($t['credit_minor'] ?? 0) - ($t['debit_minor'] ?? 0);
                }
                if ((isset($scope['start']) && $day < $scope['start']) || (isset($scope['end']) && $day > $scope['end'])) {
                    continue;
                }
                $key = $s['account_key'].':'.$day;
                if (isset($coverage[$key])) {
                    $complete = false;

                    continue;
                }
                $coverage[$key] = ['account_key' => $s['account_key'], 'date' => $day, 'closing_minor' => $balance, 'document_version_id' => $s['document_version_id']];
                if ($balance < 0) {
                    $negative[$key] = true;
                }
            }
        }

        return ['days' => $complete && $coverage ? count($negative) : null, 'coverage' => array_values($coverage), 'status' => $complete && $coverage ? 'Derived end-of-day account-days from reviewer-verified complete ledger and reconciled opening/closing. Multiple accounts count separately.' : 'Insufficient verified daily coverage; no inference from closing or sporadic running balances'];
    }

    public static function sustainability(array $tx, array $statements, array $accounts, array $policy = [], array $transfers = []): array
    {
        $out = [];
        foreach ($statements as $row) {
            $s = $row['data'];
            if (($accounts[$s['account_key']]['designation'] ?? null) !== 'business') {
                continue;
            }
            $entries = array_values(array_filter($tx, fn ($t) => $t['statement_id'] === $row['id']));
            $in = 0;
            $outflow = 0;
            $owners = 0;
            $low = null;
            $sources = [];
            $ownerAllocations = [];
            $byId = array_column($tx, null, 'id');
            foreach (['opening_balance', 'closing_balance'] as $k) {
                $v = Money::minor($s[$k] ?? null);
                if ($v !== null) {
                    $low = $low === null ? $v : min($low, $v);
                }
            }
            foreach ($entries as $t) {
                $in += $t['credit_minor'] ?? 0;
                $outflow += $t['debit_minor'] ?? 0;
                if ($t['current_category'] === 'owner_distribution') {
                    $owners += $t['debit_minor'] ?? 0;
                } else {
                    foreach ($transfers as $m) {
                        if ($m['status'] === 'confirmed' && $m['data']['source_id'] === $t['id'] && ($accounts[$byId[$m['data']['destination_id']]['account_key']]['designation'] ?? null) === 'personal') {
                            $owners += $m['allocated_minor'];
                            $ownerAllocations[] = $m['id'];
                        }
                    }
                }
                if ($t['balance_minor'] !== null) {
                    $low = $low === null ? $t['balance_minor'] : min($low, $t['balance_minor']);
                }
                $sources[] = $t['id'];
            }
            $threshold = $policy['liquidity_threshold_minor'] ?? null;
            $close = Money::minor($s['closing_balance']);
            if (($s['currency'] ?? null) !== 'USD') {
                $out[] = ['currency' => $s['currency'] ?? null, 'account_key' => $s['account_key'], 'document_version_id' => $s['document_version_id'], 'period' => [$s['period_start'], $s['period_end']], 'source_transaction_ids' => $sources, 'ending_minor' => null, 'threshold_minor' => null, 'status' => 'unsupported_currency', 'observation' => 'USD metrics only; source currency flagged, not converted or combined', 'raw_source_balances' => ['opening' => $s['opening_balance'], 'closing' => $s['closing_balance']]];

                continue;
            }
            $out[] = ['currency' => $s['currency'] ?? null, 'owner_transfer_match_ids' => $ownerAllocations, 'account_key' => $s['account_key'], 'document_version_id' => $s['document_version_id'], 'period' => [$s['period_start'], $s['period_end']], 'beginning_minor' => Money::minor($s['opening_balance']), 'ending_minor' => $close, 'inflows_minor' => $in, 'outflows_minor' => $outflow, 'owner_transfers_minor' => $owners, 'net_cash_flow_minor' => $in - $outflow, 'remaining_liquidity_minor' => $close, 'lowest_observed_minor' => $low, 'definition' => 'Net cash flow = all credits − all debits; owner transfers are a subset of debits and are NOT subtracted again. Remaining liquidity is observed closing balance.', 'source_transaction_ids' => $sources, 'coverage' => $s['complete'] ? 'extracted_statement' : 'incomplete', 'rule' => 'sustainability-v1', 'threshold_minor' => $threshold, 'threshold_status' => $threshold === null ? 'Unconfirmed; no threshold inference' : ($policy['approved_thresholds'] ?? false ? 'approved' : 'example-only'), 'observation' => $threshold !== null && $close !== null && $close < $threshold ? 'Closing liquidity below configured threshold; reviewer explanation requested' : 'Observe available balances; no automatic income adjustment', 'disposition' => 'pending_reviewer'];
        }
        $patterns = [];
        $byAccount = [];
        foreach ($out as $p) {
            $byAccount[$p['account_key']][] = $p;
        }
        foreach ($byAccount as $key => $periods) {
            usort($periods, fn ($a, $b) => strcmp($a['period'][0] ?? '', $b['period'][0] ?? ''));
            $tight = [];
            foreach ($periods as $p) {
                if ($p['threshold_minor'] !== null && $p['ending_minor'] !== null && $p['ending_minor'] < $p['threshold_minor']) {
                    $tight[] = $p;
                }
            } if (count($tight) >= 2) {
                $patterns[] = ['account_key' => $key, 'rule' => 'repeated-tight-liquidity-v1', 'observation' => 'Two or more observed statement endings below configured liquidity threshold; not a decline or income change', 'periods' => array_column($tight, 'period'), 'document_version_ids' => array_column($tight, 'document_version_id'), 'threshold_status' => $tight[0]['threshold_status'], 'disposition' => 'pending_reviewer'];
            }
        }

        return ['patterns' => $patterns, 'periods' => $out, 'notice' => 'Supports review only. No qualifying income modification or mortgage rejection.'];
    }
}
