<?php

namespace App\Bank\Fraud;

use App\Bank\Ledger;
use App\Bank\Money;
use App\Bank\ReviewState;
use App\Models\Bank\Run;

/**
 * Local signals for the fraud model. Extracted statement fields and parser
 * observations only — never the original PDF bytes.
 */
class SignalPack
{
    public function forVersion(Run $run, int $versionId): array
    {
        $statements = ReviewState::statements($run);
        $transactions = ReviewState::transactions($run);
        $pages = array_values(array_filter(Ledger::rows('bank_pages', $run), fn ($page) => (int) ($page['data']['document_version_id'] ?? 0) === $versionId));
        $findings = array_values(array_filter(Ledger::rows('bank_findings', $run), function ($finding) use ($versionId) {
            return (int) ($finding['data']['document_version_id'] ?? 0) === $versionId
                && ($finding['data']['category'] ?? '') !== 'provider_status';
        }));
        $byStatement = [];
        foreach ($transactions as $transaction) {
            $byStatement[$transaction['statement_id']][] = $transaction;
        }
        $packed = [];
        foreach ($statements as $statement) {
            $target = (int) ($statement['data']['document_version_id'] ?? 0) === $versionId;
            $packed[] = $this->statement($statement, $byStatement[$statement['id']] ?? [], $target);
        }

        return [
            'target_document_version_id' => $versionId,
            'pdf' => $this->pdf($pages),
            'statements' => $packed,
            'cross_statement' => $this->cross($statements),
            'local_indicators' => array_slice(array_map(fn ($finding) => [
                'code' => (string) ($finding['data']['code'] ?? ''),
                'observation' => mb_substr((string) ($finding['data']['observation'] ?? ''), 0, 240),
                'page' => $finding['data']['page'] ?? null,
            ], $findings), 0, 15),
            'notice' => 'Signals are review hints for a person. They are not proof of fraud.',
        ];
    }

    private function statement(array $statement, array $transactions, bool $target): array
    {
        $data = $statement['data'];
        $credits = 0;
        $debits = 0;
        $breaks = 0;
        $balance = $this->minor($data['opening_balance'] ?? null);
        $running = $balance !== null;
        $nsf = 0;
        $overdraft = 0;
        $round = [];
        $duplicates = [];
        $rows = [];
        foreach ($transactions as $transaction) {
            $credit = $transaction['credit_minor'];
            $debit = $transaction['debit_minor'];
            $credits += $credit ?? 0;
            $debits += $debit ?? 0;
            if ($running && ($credit !== null || $debit !== null)) {
                $balance += ($credit ?? 0) - ($debit ?? 0);
            } else {
                $running = false;
            }
            if ($transaction['balance_minor'] !== null && $running && abs($balance - $transaction['balance_minor']) > 1) {
                $breaks++;
            }
            if ($transaction['balance_minor'] !== null) {
                $balance = $transaction['balance_minor'];
                $running = true;
            }
            $description = strtolower((string) ($transaction['data']['description'] ?? ''));
            if (preg_match('/\bnsf\b|insufficient funds|returned item/', $description)) {
                $nsf++;
            }
            if (preg_match('/overdraft|\bod\b/', $description)) {
                $overdraft++;
            }
            if (($credit ?? 0) >= 50000 && $credit % 10000 === 0) {
                $round[] = ['amount' => Money::dollars($credit), 'date' => $transaction['posting_date'], 'page' => $transaction['data']['page'] ?? null];
            }
            if (($credit ?? 0) > 0) {
                $duplicates[$credit][] = [
                    'date' => $transaction['posting_date'],
                    'page' => $transaction['data']['page'] ?? null,
                    'description' => mb_substr(Ledger::redact((string) ($transaction['data']['description'] ?? '')), 0, 80),
                ];
            }
            if ($target && count($rows) < 60) {
                $rows[] = [
                    'date' => $transaction['posting_date'],
                    'description' => mb_substr(Ledger::redact((string) ($transaction['data']['description'] ?? '')), 0, 140),
                    'credit' => $credit === null ? null : Money::dollars($credit),
                    'debit' => $debit === null ? null : Money::dollars($debit),
                    'balance' => $transaction['balance_minor'] === null ? null : Money::dollars($transaction['balance_minor']),
                    'page' => $transaction['data']['page'] ?? null,
                ];
            }
        }
        $repeated = [];
        foreach ($duplicates as $amount => $items) {
            if (count($items) >= 2) {
                $repeated[] = ['amount' => Money::dollars((int) $amount), 'count' => count($items), 'examples' => array_slice($items, 0, 4)];
            }
        }
        $opening = $this->minor($data['opening_balance'] ?? null);
        $closing = $this->minor($data['closing_balance'] ?? null);
        $meta = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];

        return [
            'document_version_id' => $data['document_version_id'] ?? null,
            'is_target' => $target,
            'institution' => $data['institution'] ?? null,
            'holder' => $data['holder'] ?? null,
            'masked_account' => $data['masked_account'] ?? null,
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            'opening_balance' => $data['opening_balance'] ?? null,
            'closing_balance' => $data['closing_balance'] ?? null,
            'pdf_metadata' => $this->metadata($meta),
            'math' => [
                'running_balance_breaks' => $breaks,
                'opening_plus_activity_matches_closing' => ($opening !== null && $closing !== null) ? abs($opening + $credits - $debits - $closing) <= 1 : null,
            ],
            'deposits' => [
                'nsf_count' => $nsf,
                'overdraft_count' => $overdraft,
                'round_number_deposits' => array_slice($round, 0, 8),
                'duplicate_deposit_amounts' => array_slice($repeated, 0, 8),
            ],
            'transactions' => $rows,
        ];
    }

    private function pdf(array $pages): array
    {
        $fonts = [];
        $structural = [];
        $meta = [];
        foreach ($pages as $page) {
            $data = $page['data'];
            $number = $data['page'] ?? null;
            foreach ($data['fonts'] ?? [] as $font) {
                $fonts[$number][] = (string) $font;
            }
            foreach ($data['structural'] ?? [] as $code) {
                $structural[(string) $code] = true;
            }
            if ($meta === [] && is_array($data['metadata'] ?? null)) {
                $meta = $data['metadata'];
            }
        }
        $notes = [];
        foreach ($fonts as $number => $names) {
            $unique = array_values(array_unique($names));
            if (count($unique) > 4) {
                $notes[] = ['page' => $number, 'reason' => 'more_than_four_font_names', 'fonts' => array_slice($unique, 0, 8)];
            }
        }

        return [
            'page_count' => count($pages),
            'metadata' => $this->metadata($meta),
            'structural' => array_keys($structural),
            'font_notes' => array_slice($notes, 0, 10),
            'fonts_by_page' => $fonts,
        ];
    }

    private function cross(array $statements): array
    {
        $groups = [];
        $byHolder = [];
        foreach ($statements as $statement) {
            $data = $statement['data'];
            $groups[($data['institution'] ?? '').'|'.($data['masked_account'] ?? '')][] = $data;
            $holder = trim((string) ($data['holder'] ?? ''));
            if ($holder !== '') {
                $byHolder[$holder][] = (string) ($data['masked_account'] ?? '');
            }
        }
        $holders = [];
        $gaps = [];
        foreach ($groups as $key => $list) {
            $names = array_values(array_unique(array_map(fn ($data) => (string) ($data['holder'] ?? ''), $list)));
            if (count(array_filter($names, fn ($name) => $name !== '')) > 1) {
                $holders[] = ['group' => $key, 'holders' => $names, 'document_version_ids' => array_column($list, 'document_version_id')];
            }
            usort($list, fn ($a, $b) => strcmp((string) ($a['period_start'] ?? ''), (string) ($b['period_start'] ?? '')));
            $previous = null;
            foreach ($list as $data) {
                if ($previous && ! empty($data['period_start']) && ! empty($previous['period_end'])) {
                    try {
                        $next = (new \DateTimeImmutable($previous['period_end']))->modify('+1 day')->format('Y-m-d');
                    } catch (\Throwable) {
                        $previous = $data;

                        continue;
                    }
                    if ($data['period_start'] !== $next) {
                        $gaps[] = [
                            'from' => $previous['period_end'],
                            'to' => $data['period_start'],
                            'kind' => $data['period_start'] < $next ? 'overlap' : 'gap',
                            'document_version_ids' => [$previous['document_version_id'] ?? null, $data['document_version_id'] ?? null],
                        ];
                    }
                }
                $previous = $data;
            }
        }
        $accounts = [];
        foreach ($byHolder as $holder => $masks) {
            $unique = array_values(array_unique($masks));
            if (count($unique) > 1) {
                $accounts[] = ['holder' => $holder, 'masked_accounts' => $unique];
            }
        }

        return [
            'holder_or_account_mismatches' => $holders,
            'same_name_different_accounts' => $accounts,
            'date_gaps' => $gaps,
        ];
    }

    private function metadata(array $meta): array
    {
        return [
            'producer' => $meta['producer'] ?? null,
            'creator' => $meta['creator'] ?? null,
            'creationDate' => $meta['creationDate'] ?? null,
            'modDate' => $meta['modDate'] ?? null,
            'dates_differ' => ! empty($meta['creationDate']) && ! empty($meta['modDate']) && $meta['creationDate'] !== $meta['modDate'],
        ];
    }

    private function minor(mixed $value): ?int
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return Money::minor($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
