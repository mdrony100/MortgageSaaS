<?php

namespace App\Bank;

use App\Bank\ProcessingException as RuntimeException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ExtractionSchema
{
    public const VERSION = 'statement-v1';

    public static function jsonSchema(): array
    {
        $text = ['type' => ['string', 'null']];
        $money = ['type' => ['string', 'null']];
        $tx = ['posting_date' => $text, 'transaction_date' => $text, 'description' => ['type' => 'string'], 'credit' => $money, 'debit' => $money, 'original_amount' => $text, 'running_balance' => $money, 'reference' => $text, 'page' => ['type' => 'integer'], 'source_row' => ['type' => 'integer'], 'excerpt' => ['type' => 'string'], 'category_suggestion' => ['type' => 'string', 'enum' => config('bank.categories')], 'suggestion_explanation' => $text];
        $props = ['document_type' => ['type' => 'string', 'enum' => ['bank_statement', 'other', 'unresolved']], 'schema_version' => ['type' => 'string', 'enum' => [self::VERSION]], 'institution' => $text, 'holder' => $text, 'masked_account' => $text, 'currency' => $text, 'designation' => ['type' => ['string', 'null'], 'enum' => ['personal', 'business', null]], 'designation_source' => $text, 'period_start' => $text, 'period_end' => $text, 'opening_balance' => $money, 'closing_balance' => $money, 'reported_credits' => $money, 'reported_debits' => $money, 'reported_fees' => $money, 'fees_separate_from_debits' => ['type' => ['boolean', 'null']], 'page_coverage' => ['type' => 'array', 'items' => ['type' => 'integer']], 'complete' => ['type' => 'boolean'], 'transactions' => ['type' => 'array', 'items' => ['type' => 'object', 'additionalProperties' => false, 'properties' => $tx, 'required' => array_keys($tx)]]];

        return ['type' => 'object', 'additionalProperties' => false, 'properties' => $props, 'required' => array_keys($props)];
    }

    public static function validate(array $d, array $pages): array
    {
        $fields = array_keys(self::jsonSchema()['properties']);
        if (array_diff($fields, array_keys($d)) || array_diff(array_keys($d), $fields)) {
            throw new RuntimeException('Extraction schema fields invalid');
        }
        $rules = ['document_type' => 'required|in:bank_statement,other,unresolved', 'schema_version' => 'required|in:'.self::VERSION, 'institution' => 'nullable|string|max:200', 'holder' => 'nullable|string|max:200', 'masked_account' => 'nullable|string|max:80', 'currency' => 'nullable|string|size:3', 'designation' => ['nullable', Rule::in(['personal', 'business'])], 'designation_source' => 'nullable|string|max:1000', 'period_start' => 'nullable|string|max:20', 'period_end' => 'nullable|string|max:20', 'fees_separate_from_debits' => 'nullable|boolean', 'complete' => 'required|boolean', 'page_coverage' => 'required|array', 'page_coverage.*' => 'integer', 'transactions' => 'present|array|max:20000', 'transactions.*.posting_date' => 'nullable|string|max:20', 'transactions.*.transaction_date' => 'nullable|string|max:20', 'transactions.*.description' => 'present|string|max:2000', 'transactions.*.page' => ['required', 'integer', Rule::in($pages)], 'transactions.*.source_row' => 'required|integer|min:1|max:100000', 'transactions.*.excerpt' => 'present|string|max:4000', 'transactions.*.reference' => 'nullable|string|max:200', 'transactions.*.original_amount' => 'nullable|string|max:200', 'transactions.*.category_suggestion' => ['required', Rule::in(config('bank.categories'))], 'transactions.*.suggestion_explanation' => 'nullable|string|max:2000'];
        foreach (['opening_balance', 'closing_balance', 'reported_credits', 'reported_debits', 'reported_fees'] as $k) {
            $rules[$k] = 'nullable|string|regex:/^-?\d{1,12}(\.\d{1,2})?$/D';
        }
        foreach (['credit', 'debit', 'running_balance'] as $k) {
            $rules['transactions.*.'.$k] = 'nullable|string|regex:/^-?\d{1,12}(\.\d{1,2})?$/D';
        }
        if (Validator::make($d, $rules)->fails()) {
            throw new RuntimeException('Extraction schema validation failed; source review required');
        }
        $covered = $d['page_coverage'];
        sort($covered);
        $expected = $pages;
        sort($expected);
        if ($covered !== $expected) {
            throw new RuntimeException('Extraction page manifest incomplete or duplicated');
        }
        $d['masked_account'] = $d['masked_account'] === null ? null : '****'.substr(preg_replace('/\D/', '', $d['masked_account']), -4);
        foreach (['institution', 'holder', 'designation_source'] as $k) {
            if ($d[$k] !== null) {
                $d[$k] = Ledger::redact($d[$k]);
            }
        }
        foreach ($d['transactions'] as &$t) {
            foreach (['description', 'reference', 'excerpt', 'original_amount', 'suggestion_explanation'] as $k) {
                if (isset($t[$k])) {
                    $t[$k] = Ledger::redact($t[$k]);
                }
            }
            $t['region'] = null;
            $t['region_verified'] = false; // AI coordinates are not accepted as exact localization.
            if (($t['credit'] !== null && Money::minor($t['credit']) < 0) || ($t['debit'] !== null && Money::minor($t['debit']) < 0) || (($t['credit'] ?? '0') !== '0' && ($t['debit'] ?? '0') !== '0' && Money::minor($t['credit']) > 0 && Money::minor($t['debit']) > 0)) {
                throw new RuntimeException('Ambiguous credit/debit extraction');
            }
        } unset($t);

        return $d;
    }

    public static function date(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $s);

        return $d && $d->format('Y-m-d') === $s ? $s : null;
    }
}
