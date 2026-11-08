<?php

namespace App\Bank;

use App\Models\Bank\Run;

class InternalIntegrity
{
    public function analyze(Run $r, array $policy): array
    {
        $out = [];
        $statements = ReviewState::statements($r);
        $accounts = ReviewState::accounts($r);
        $tx = ReviewState::transactions($r);
        $maskGroups = [];
        foreach ($statements as $s) {
            $d = $s['data'];
            $maskGroups[($d['institution'] ?? '').':'.($d['masked_account'] ?? '')][] = $d;
            $meta = $d['metadata'] ?? [];
            if (! empty($meta['creationDate']) && ! empty($meta['modDate']) && $meta['creationDate'] !== $meta['modDate']) {
                $out[] = ['category' => 'integrity_indicator', 'code' => 'metadata_modified', 'observation' => 'PDF modification and creation dates differ; routine PDF processing can also cause this, not proof of alteration', 'document_version_id' => $d['document_version_id']];
            }
        }
        foreach ($maskGroups as $list) {
            if (count(array_unique(array_column($list, 'holder'))) > 1) {
                $out[] = ['category' => 'financial_inconsistency', 'code' => 'account_holder_inconsistent', 'observation' => 'Statements share institution/masked suffix but have different holder names; do not merge identities without source verification', 'document_version_ids' => array_column($list, 'document_version_id')];
            }
        }
        $groups = [];
        $cash = [];
        foreach ($tx as $t) {
            if (($t['credit_minor'] ?? 0) > 0) {
                $groups[$t['account_key'].':'.$t['credit_minor']][] = $t;
                if ($t['current_category'] === 'cash_deposit') {
                    $cash[$t['account_key'].':'.substr($t['posting_date'] ?? '', 0, 7)][] = $t;
                }
            }
        }
        if (isset($policy['repeat_amount_count'])) {
            foreach ($groups as $group) {
                if (count($group) >= $policy['repeat_amount_count']) {
                    $out[] = ['category' => 'integrity_indicator', 'code' => 'configured_repeated_amount', 'observation' => 'Repeated credit amounts meet configured review convention; legitimate repeats retained, not proof', 'transaction_ids' => array_column($group, 'id'), 'threshold_status' => ($policy['approved_thresholds'] ?? false) ? 'approved' : 'example-only'];
                }
            }
        }
        if (isset($policy['cash_pattern_threshold_minor'])) {
            foreach ($cash as $group) {
                if (array_sum(array_column($group, 'credit_minor')) >= $policy['cash_pattern_threshold_minor']) {
                    $out[] = ['category' => 'integrity_indicator', 'code' => 'configured_cash_pattern', 'observation' => 'Cash deposit total meets configured review threshold; request context, not a fraud conclusion', 'transaction_ids' => array_column($group, 'id'), 'threshold_status' => ($policy['approved_thresholds'] ?? false) ? 'approved' : 'example-only'];
                }
            }
        }
        $matches = array_values(array_filter(ReviewState::transfers($r), fn ($m) => $m['status'] === 'confirmed'));
        $byId = array_column($tx, null, 'id');
        if (isset($policy['circular_window_days'])) {
            foreach ($matches as $i => $a) {
                foreach (array_slice($matches, $i + 1) as $b) {
                    $as = $byId[$a['data']['source_id']];
                    $ad = $byId[$a['data']['destination_id']];
                    $bs = $byId[$b['data']['source_id']];
                    $bd = $byId[$b['data']['destination_id']];
                    if ($as['account_key'] === $bd['account_key'] && $ad['account_key'] === $bs['account_key'] && $a['allocated_minor'] === $b['allocated_minor'] && abs((new \DateTimeImmutable($as['posting_date']))->diff(new \DateTimeImmutable($bs['posting_date']))->days) <= $policy['circular_window_days']) {
                        $out[] = ['category' => 'integrity_indicator', 'code' => 'configured_circular_flow', 'observation' => 'Confirmed reciprocal movements within configured window; review business context, not proof', 'transfer_match_ids' => [$a['id'], $b['id']], 'transaction_ids' => [$as['id'], $ad['id'], $bs['id'], $bd['id']]];
                    }
                }
            }
        }

        return $out;
    }
}
