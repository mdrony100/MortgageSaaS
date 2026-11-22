<?php

namespace App\Bank\Fraud;

use Illuminate\Support\Facades\Validator;

class ResultSchema
{
    public static function jsonSchema(): array
    {
        $finding = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'title' => ['type' => 'string'],
                'evidence' => ['type' => 'string'],
                'page' => ['type' => ['integer', 'null']],
            ],
            'required' => ['title', 'evidence', 'page'],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'risk_level' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'score' => ['type' => 'integer'],
                'summary' => ['type' => 'string'],
                'findings' => ['type' => 'array', 'items' => $finding],
            ],
            'required' => ['risk_level', 'score', 'summary', 'findings'],
        ];
    }

    public static function validate(mixed $data): array
    {
        if (! is_array($data)) {
            throw new FraudCheckException('The AI fraud check returned a result that could not be read. Run it again.', true);
        }
        $fields = ['risk_level', 'score', 'summary', 'findings'];
        if (array_diff($fields, array_keys($data)) || array_diff(array_keys($data), $fields)) {
            throw new FraudCheckException('The AI fraud check returned a result that could not be read. Run it again.', true);
        }
        if (! is_array($data['findings'])) {
            throw new FraudCheckException('The AI fraud check returned a result that could not be read. Run it again.', true);
        }
        foreach ($data['findings'] as $finding) {
            if (! is_array($finding) || array_diff(['title', 'evidence', 'page'], array_keys($finding)) || array_diff(array_keys($finding), ['title', 'evidence', 'page'])) {
                throw new FraudCheckException('The AI fraud check returned a result that could not be read. Run it again.', true);
            }
        }
        $validator = Validator::make($data, [
            'risk_level' => 'required|in:low,medium,high',
            'score' => 'required|integer|min:0|max:100',
            'summary' => 'required|string|min:1|max:600',
            'findings' => 'present|array|max:20',
            'findings.*.title' => 'required|string|min:1|max:160',
            'findings.*.evidence' => 'required|string|min:1|max:500',
            'findings.*.page' => 'nullable|integer|min:1|max:200',
        ]);
        if ($validator->fails()) {
            throw new FraudCheckException('The AI fraud check returned a result that could not be read. Run it again.', true);
        }
        $findings = [];
        foreach ($data['findings'] as $finding) {
            $findings[] = [
                'title' => trim($finding['title']),
                'evidence' => trim($finding['evidence']),
                'page' => $finding['page'] === null ? null : (int) $finding['page'],
            ];
        }

        return [
            'risk_level' => $data['risk_level'],
            'score' => (int) $data['score'],
            'summary' => trim($data['summary']),
            'findings' => $findings,
        ];
    }
}
