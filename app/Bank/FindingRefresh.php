<?php

namespace App\Bank;

use App\Models\Bank\Run;

class FindingRefresh
{
    private function append(Run $r, array $f): void
    {
        Ledger::append('bank_findings', $r, 'evidence:'.Ledger::hash($f), $f + ['severity' => 'review', 'rule' => 'internal-v1', 'explanation' => 'Review indicator, not proof or an automatic loan decision', 'action' => 'Review original evidence and record disposition', 'status' => 'open']);
    }

    public function internal(Run $r, array $policy): void
    {
        foreach (app(InternalIntegrity::class)->analyze($r, $policy) as $f) {
            $this->append($r, $f);
        }
    }

    public function metrics(Run $r, array $snapshot): void
    {
        if ($snapshot['account_keys']) {
            foreach (['nsf', 'supporting_account_nsf'] as $scope) {
                foreach ($snapshot[$scope]['review_conditions'] ?? [] as $f) {
                    $this->append($r, $f + ['category' => 'nsf_review', 'code' => 'nsf_threshold', 'metric_scope' => $scope]);
                }
            }
        }
        foreach ($snapshot['sustainability']['patterns'] ?? [] as $f) {
            $this->append($r, $f + ['category' => 'sustainability_review', 'code' => 'tight_liquidity']);
        }
        foreach ($snapshot['sustainability']['periods'] ?? [] as $p) {
            if ($p['threshold_minor'] !== null && $p['ending_minor'] !== null && $p['ending_minor'] < $p['threshold_minor']) {
                $this->append($r, ['category' => 'sustainability_review', 'code' => 'closing_liquidity', 'observation' => $p['observation'], 'document_version_id' => $p['document_version_id'], 'account_key' => $p['account_key'], 'period' => $p['period'], 'source_transaction_ids' => $p['source_transaction_ids'], 'threshold_minor' => $p['threshold_minor'], 'threshold_status' => $p['threshold_status'], 'rule' => $p['rule']]);
            }
        }
    }
}
