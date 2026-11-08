<?php

namespace App\Bank;

class Reconcile
{
    public static function statement(array $s, array $tx, int $tolerance = 1): array
    {
        $issues = [];
        $add = function ($code, $category, $observation) use (&$issues, $tolerance) {
            $issues[] = ['code' => $code, 'category' => $category, 'observation' => $observation, 'rule' => 'reconciliation-v1: tolerance '.$tolerance.' minor units (decimal extraction/statement rounding only)'];
        };
        $credits = 0;
        $debits = 0;
        $balance = Money::minor($s['opening_balance']);
        $running = $balance !== null;
        if (($s['document_type'] ?? 'unresolved') !== 'bank_statement') {
            $add('document_type_unresolved', 'extraction_uncertainty', 'Document type is not verified as a bank statement');
        }
        if (! $s['complete']) {
            $add('incomplete_extraction', 'extraction_uncertainty', 'Native/vision extraction is incomplete; verify pages and unresolved values');
        }
        if (! $s['currency'] || $s['currency'] !== 'USD') {
            $add('unsupported_currency', 'missing_evidence', 'USD calculation only; no currency conversion performed');
        }
        if (! ExtractionSchema::date($s['period_start']) || ! ExtractionSchema::date($s['period_end']) || $s['period_start'] > $s['period_end']) {
            $add('invalid_period', 'extraction_uncertainty', 'Statement period is missing or invalid');
        }
        foreach ($tx as $t) {
            $c = $t['credit_minor'];
            $d = $t['debit_minor'];
            if ($c === null && $d === null) {
                $add('unreadable_amount', 'extraction_uncertainty', 'Transaction amount unreadable; no digits inferred');
            }
            $credits += $c ?? 0;
            $debits += $d ?? 0;
            if (! $t['posting_date'] || ! $s['period_start'] || ! $s['period_end'] || $t['posting_date'] < $s['period_start'] || $t['posting_date'] > $s['period_end']) {
                $add('transaction_date', 'extraction_uncertainty', 'Missing, invalid or out-of-period posting date');
            }
            if ($balance !== null && ($c !== null || $d !== null)) {
                $balance += ($c ?? 0) - ($d ?? 0);
            } else {
                $running = false;
            }
            if ($t['balance_minor'] !== null && $running && abs($balance - $t['balance_minor']) > $tolerance) {
                $add('running_balance', 'financial_inconsistency', 'Running balance does not reconcile with source order');
            }
            if ($t['balance_minor'] !== null) {
                $balance = $t['balance_minor'];
            }
        }
        $open = Money::minor($s['opening_balance']);
        $close = Money::minor($s['closing_balance']);
        if ($open === null || $close === null) {
            $add('balances_missing', 'missing_evidence', 'Opening or closing balance unavailable');
        } elseif (abs($open + $credits - $debits - $close) > $tolerance) {
            $add('balance_arithmetic', 'financial_inconsistency', 'Opening + extracted credits − all extracted debits differs from closing; not a fraud conclusion');
        }
        if ($s['reported_credits'] !== null && abs(Money::minor($s['reported_credits']) - $credits) > $tolerance) {
            $add('credit_total', 'financial_inconsistency', 'Extracted credits differ from reported total');
        }
        if ($s['reported_debits'] !== null) {
            $fees = Money::minor($s['reported_fees']);
            $reported = Money::minor($s['reported_debits']);
            if ($fees && $s['fees_separate_from_debits'] === null) {
                $add('fee_convention', 'extraction_uncertainty', 'Separately reported fees require explicit treatment; no fee assumed twice');
            } else {
                if ($s['fees_separate_from_debits'] === true) {
                    $reported += $fees ?? 0;
                } if (abs($reported - $debits) > $tolerance) {
                    $add('debit_total', 'financial_inconsistency', 'Extracted debits (including fees) differ from reported debits and separately excluded fees');
                }
            }
        }

        return $issues;
    }

    public static function continuity(array $statements, int $tolerance = 1): array
    {
        $groups = [];
        $out = [];
        foreach ($statements as $s) {
            $groups[$s['data']['account_key']][] = $s;
        }
        foreach ($groups as $key => $list) {
            usort($list, fn ($a, $b) => strcmp($a['data']['period_start'] ?? '', $b['data']['period_start'] ?? ''));
            $prev = null;
            foreach ($list as $row) {
                $s = $row['data'];
                if ($prev && ExtractionSchema::date($s['period_start']) && ExtractionSchema::date($prev['period_end'])) {
                    $next = (new \DateTimeImmutable($prev['period_end']))->modify('+1 day')->format('Y-m-d');
                    if ($s['period_start'] !== $next) {
                        $out[] = ['code' => $s['period_start'] < $next ? 'overlap_or_duplicate_statement' : 'missing_statement_period', 'category' => 'missing_evidence', 'observation' => 'Statement periods overlap/duplicate or have a gap', 'account_key' => $key, 'document_version_id' => $s['document_version_id']];
                    }
                    if ($s['opening_balance'] !== null && $prev['closing_balance'] !== null && abs(Money::minor($s['opening_balance']) - Money::minor($prev['closing_balance'])) > $tolerance) {
                        $out[] = ['code' => 'balance_continuity', 'category' => 'financial_inconsistency', 'observation' => 'Opening balance does not continue previous closing', 'account_key' => $key, 'document_version_id' => $s['document_version_id']];
                    }
                    if ($s['holder'] !== $prev['holder'] || $s['institution'] !== $prev['institution']) {
                        $out[] = ['code' => 'account_information', 'category' => 'financial_inconsistency', 'observation' => 'Account information inconsistent across statements', 'account_key' => $key, 'document_version_id' => $s['document_version_id']];
                    }
                } $prev = $s;
            }
        }

        return $out;
    }
}
