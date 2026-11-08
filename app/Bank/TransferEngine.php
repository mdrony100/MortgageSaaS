<?php

namespace App\Bank;

use App\Models\Bank\Run;

class TransferEngine
{
    public const VERSION = 'transfer-v1';

    public function match(Run $run): array
    {
        $rels = Ledger::latestDecisions('bank_relationships', $run, 'pair');
        $accounts = ReviewState::accounts($run);
        $tx = ReviewState::transactions($run);
        $out = [];
        $credits = [];
        $comparisons = 0;
        $window = (int) ($run->options['transfer_window_days'] ?? 3);
        foreach ($tx as $entry) {
            if (($entry['credit_minor'] ?? 0) > 0 && $entry['posting_date']) {
                $credits[$entry['posting_date']][] = $entry;
            }
        }
        foreach ($tx as $from) {
            if (($from['debit_minor'] ?? 0) <= 0 || ! $from['posting_date']) {
                continue;
            }
            $candidates = [];
            for ($offset = -$window; $offset <= $window; $offset++) {
                $day = (new \DateTimeImmutable($from['posting_date']))->modify(($offset >= 0 ? '+' : '').$offset.' days')->format('Y-m-d');
                foreach ($credits[$day] ?? [] as $candidate) {
                    $candidates[] = $candidate;
                }
            }
            foreach ($candidates as $to) {
                if (++$comparisons > config('bank.transfer_comparison_limit') || count($out) >= config('bank.transfer_candidate_limit')) {
                    Ledger::append('bank_findings', $run, 'transfer-search-limit', ['category' => 'missing_evidence', 'severity' => 'review', 'code' => 'transfer_search_limit', 'rule' => self::VERSION, 'observation' => 'Transfer candidate resource limit reached; narrow/review account evidence explicitly', 'explanation' => 'Unsearched candidates are not verified as absent', 'action' => 'Resolve bounded search evidence with reviewer notes', 'status' => 'open']);

                    return $out;
                }
                if (($to['credit_minor'] ?? 0) <= 0 || ! $to['posting_date'] || $from['account_key'] === $to['account_key']) {
                    continue;
                }
                $pair = [$from['account_key'], $to['account_key']];
                sort($pair);
                $pair = implode(':', $pair);
                if (! ($rels[$pair]['data']['verified'] ?? false) || ! ($accounts[$from['account_key']]['verified'] ?? false) || ! ($accounts[$to['account_key']]['verified'] ?? false)) {
                    continue;
                }
                $window = (int) ($run->options['transfer_window_days'] ?? 3);
                $days = abs((new \DateTimeImmutable($from['posting_date']))->diff(new \DateTimeImmutable($to['posting_date']))->days);
                if ($days > $window) {
                    continue;
                }
                $ref = $from['data']['reference'] ?? null;
                $other = $to['data']['reference'] ?? null;
                $reference = $ref !== null && strlen($ref) >= 3 && $ref === $other;
                $last4 = substr($accounts[$to['account_key']]['masked_account'] ?? '', -4);
                $description = $last4 !== '' && str_contains($from['data']['description'], $last4) && preg_match('/transfer|distribution/i', $from['data']['description'].' '.$to['data']['description']);
                $equal = $from['debit_minor'] === $to['credit_minor'];
                if (! $equal && ! $reference && ! $description) {
                    continue;
                }
                $d = ['source_id' => $from['id'], 'destination_id' => $to['id'], 'amount_minor' => min($from['debit_minor'], $to['credit_minor']), 'status' => $equal && ($reference || $description) ? 'proposed' : 'ambiguous', 'evidence' => ['verified_relationship_id' => $rels[$pair]['id'], 'date_window_days' => $window, 'date_distance_days' => $days, 'shared_reference' => $reference, 'account_description' => $description, 'equal_amount' => $equal], 'rule' => self::VERSION, 'notice' => $equal ? 'Equal amount alone is not proof' : 'Possible split/combined/partial transfer; explicit review required'];
                $out[] = Ledger::append('bank_transfer_matches', $run, $from['id'].':'.$to['id'], $d);
            }
        }

        return $out;
    }
}
