<?php

namespace App\Bank;

use App\Models\Bank\Run;

class ReviewState
{
    public static function transactions(Run $r): array
    {
        $reviews = Ledger::latestDecisions('bank_reviews', $r, 'target');
        $corrections = [];
        $applied = [];
        $out = [];
        $statementAccounts = [];
        foreach (self::statements($r) as $s) {
            $statementAccounts[$s['id']] = $s['data']['account_key'];
        }
        foreach (Ledger::rows('bank_reviews', $r) as $decision) {
            if ($decision['data']['kind'] === 'transaction') {
                $target = $decision['data']['target'];
                $corrections[$target] = array_replace($corrections[$target] ?? [], $decision['data']['correction'] ?? []);
                $applied[$target][] = $decision['id'];
            }
        }
        foreach (Ledger::rows('bank_transactions', $r) as $t) {
            $v = $reviews['transaction:'.$t['id']] ?? null;
            $t['account_key'] = $statementAccounts[$t['statement_id']] ?? $t['account_key'];
            $t['current_category'] = $v['data']['category'] ?? $t['data']['initial_category'];
            $t['original_values'] = ['posting_date' => $t['posting_date'], 'credit_minor' => $t['credit_minor'], 'debit_minor' => $t['debit_minor'], 'balance_minor' => $t['balance_minor']];
            foreach ($corrections['transaction:'.$t['id']] ?? [] as $field => $value) {
                if ($field === 'posting_date') {
                    $t['posting_date'] = ExtractionSchema::date($value);
                } else {
                    $t[match ($field) {
                        'credit' => 'credit_minor','debit' => 'debit_minor','running_balance' => 'balance_minor'
                    }] = Money::minor($value);
                }
            }
            $t['include'] = $v['data']['include'] ?? null;
            $t['review_id'] = $v['id'] ?? null;
            $t['applied_review_ids'] = $applied['transaction:'.$t['id']] ?? [];
            $out[] = $t;
        }

        return $out;
    }

    public static function statements(Run $r): array
    {
        $states = [];
        $dailyIds = [];
        $correctionIds = [];
        $transactionStatements = [];
        foreach (Ledger::rows('bank_transactions', $r) as $t) {
            $transactionStatements['transaction:'.$t['id']] = $t['statement_id'];
        }
        foreach (Ledger::rows('bank_reviews', $r) as $row) {
            $d = $row['data'];
            $target = $d['target'];
            if ($d['kind'] === 'statement') {
                $states[$target]['statement_correction'] = array_replace($states[$target]['statement_correction'] ?? [], $d['statement_correction'] ?? []);
                if (! empty($d['statement_correction'])) {
                    $states[$target]['daily_reconstruction_verified'] = false;
                }
                if (isset($d['canonical_account_key'])) {
                    $states[$target]['canonical_account_key'] = $d['canonical_account_key'];
                }
                if (array_key_exists('daily_reconstruction_verified', $d)) {
                    $states[$target]['daily_reconstruction_verified'] = $d['daily_reconstruction_verified'];
                    $dailyIds[$target] = $row['id'];
                }
            } elseif ($d['kind'] === 'transaction' && ! empty($d['correction']) && isset($transactionStatements[$target])) {
                $correctionIds[$transactionStatements[$target]] = $row['id'];
            }
        }
        $out = [];
        foreach (Ledger::rows('bank_statements', $r) as $s) {
            $target = 'statement:'.$s['id'];
            $review = $states[$target] ?? [];
            $s['original_values'] = $s['data'];
            foreach ($review['statement_correction'] ?? [] as $k => $v) {
                $s['data'][$k] = $v;
            }
            if (isset($review['canonical_account_key'])) {
                $s['data']['account_key'] = $review['canonical_account_key'];
            }
            $s['data']['daily_reconstruction_verified'] = ($review['daily_reconstruction_verified'] ?? false) && ($correctionIds[$s['id']] ?? 0) <= ($dailyIds[$target] ?? 0);
            $out[] = $s;
        }

        return $out;
    }

    public static function accounts(Run $r): array
    {
        $reviews = Ledger::latestDecisions('bank_reviews', $r, 'target');
        $out = [];
        $statements = self::statements($r);
        $keys = [];
        $identityEdits = [];
        foreach ($statements as $s) {
            $keys[$s['id']] = $s['data']['account_key'];
        }
        foreach (Ledger::rows('bank_reviews', $r) as $row) {
            $d = $row['data'];
            if ($d['kind'] === 'statement' && (! empty(array_intersect(array_keys($d['statement_correction'] ?? []), ['institution', 'holder', 'masked_account'])) || isset($d['canonical_account_key']))) {
                $identityEdits[$keys[$d['statement_id']]] = $row['id'];
            }
        }
        foreach ($statements as $s) {
            $a = $s['data']['account_key'];
            if (isset($out[$a]) && $s['original_values']['account_key'] !== $a) {
                continue;
            } $review = $reviews['account:'.$a] ?? null;
            $out[$a] = ['account_key' => $a, 'masked_account' => $s['data']['masked_account'], 'holder' => $s['data']['holder'], 'institution' => $s['data']['institution'], 'designation' => $review['data']['designation'] ?? $s['data']['designation'], 'verified' => ($review['data']['verified'] ?? false) && ($review['id'] ?? 0) >= ($identityEdits[$a] ?? 0), 'review_id' => $review['id'] ?? null];
        }

        return $out;
    }

    public static function transfers(Run $r): array
    {
        $reviews = Ledger::latestDecisions('bank_transfer_reviews', $r, 'match_id');
        $relationships = Ledger::latestDecisions('bank_relationships', $r, 'pair');
        $accounts = self::accounts($r);
        $entries = array_column(self::transactions($r), null, 'id');

        return array_map(function ($m) use ($reviews, $relationships, $accounts, $entries) {
            $v = $reviews[$m['id']] ?? null;
            $status = $v['data']['status'] ?? $m['data']['status'];
            $amount = $v['data']['amount_minor'] ?? 0;
            $from = $entries[$m['data']['source_id']];
            $to = $entries[$m['data']['destination_id']];
            $pair = [$from['account_key'], $to['account_key']];
            sort($pair);
            if ($status === 'confirmed' && (! ($relationships[implode(':', $pair)]['data']['verified'] ?? false) || ! $accounts[$from['account_key']]['verified'] || ! $accounts[$to['account_key']]['verified'])) {
                $status = 'relationship_unverified';
                $amount = 0;
            }

            return $m + ['status' => $status, 'allocated_minor' => $amount, 'review_id' => $v['id'] ?? null];
        }, Ledger::rows('bank_transfer_matches', $r));
    }

    public static function findings(Run $r): array
    {
        $reviews = Ledger::latestDecisions('bank_finding_reviews', $r, 'finding_id');

        return array_map(fn ($f) => $f + ['status' => $reviews[$f['id']]['data']['status'] ?? 'open', 'assignee' => $reviews[$f['id']]['data']['assignee'] ?? null, 'disposition' => $reviews[$f['id']]['data'] ?? null], Ledger::rows('bank_findings', $r));
    }
}
