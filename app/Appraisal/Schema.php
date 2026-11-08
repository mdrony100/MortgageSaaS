<?php

namespace App\Appraisal;

use App\Bank\ProcessingException;

class Schema
{
    public static function vocabulary(): string
    {
        return 'Use canonical keys: classification,form,form_version,address,parcel,legal_description,property_type,units,zoning,legal_use,site_area,site_area_units,access,utilities,flood,adu,accessory_structures,mixed_use,agricultural_use,condo,coop,parcels,excess_land,external_influences,marketability,allocation,borrower,entity,loan_occupancy,inspection_occupancy,current_occupancy,lease_status,transaction,contract_price,loan_amount,sale_history,ownership_history,transfer_date,program,appraiser,inspection_date,effective_date,report_date,appraised_value,value_condition,subject_to,condition,quality,repairs,deferred_maintenance,completion,market_trends,marketing_time,comparables,comparable_rents,adjustments,concessions,reconciliation,signature_presence,certification; lease.address,lease.unit,lease.parties,lease.rent,lease.start,lease.end,lease.signature_presence,lease.continuation,lease.concessions,lease.utilities,lease.amendments; rent_roll.rent,market_rent,rental_schedule.market_rent; comparable.price,comparable.sale_date,comparable.distance,comparable.net_adjustment,comparable.gross_adjustment,comparable.adjusted_value,comparable.explanation. Use unit for explicit unit identity only, or comp-N for comparable identity. Normalize amounts to decimal strings without currency/thousands separators, dates to ISO only when unambiguous; retain raw text/units. Do not invent a unit, date or value.';
    }

    public static function json(): array
    {
        $str = ['type' => ['string', 'null']];
        $obj = fn ($p) => ['type' => 'object', 'properties' => $p, 'required' => array_keys($p), 'additionalProperties' => false];

        return $obj(['classification' => $str, 'form' => $str, 'form_version' => $str, 'complete' => ['type' => 'boolean'], 'pages' => ['type' => 'array', 'items' => ['type' => 'integer']], 'fields' => ['type' => 'array', 'items' => $obj(['key' => ['type' => 'string'], 'unit' => $str, 'raw' => $str, 'normalized' => $str, 'units' => $str, 'page' => ['type' => 'integer'], 'excerpt' => ['type' => 'string'], 'status' => ['type' => 'string', 'enum' => ['extracted', 'extracted_unverified', 'uncertain', 'missing', 'unreadable', 'native_supported_layout']]])], 'photos' => ['type' => 'array', 'items' => $obj(['page' => ['type' => 'integer'], 'unit' => $str, 'date' => $str, 'caption' => $str, 'narrative' => $str, 'observation' => ['type' => 'string'], 'why_verify' => ['type' => 'string'], 'uncertainty' => ['type' => 'string']])], 'issues' => ['type' => 'array', 'items' => $obj(['category' => ['type' => 'string'], 'observation' => ['type' => 'string'], 'page' => ['type' => ['integer', 'null']], 'what_verify' => ['type' => 'string']])]]);
    }

    public static function validate(array $a, array $pages): array
    {
        self::shape($a, self::json());
        foreach (self::json()['required'] as $k) {
            if (! array_key_exists($k, $a)) {
                throw new ProcessingException('Appraisal extraction schema incomplete');
            }
        }$cov = $a['pages'];
        sort($cov);
        $expected = $pages;
        sort($expected);
        if ($cov !== $expected || count($cov) !== count(array_unique($cov)) || count($a['fields']) > config('review.max_fields') || count($a['photos']) > config('review.max_photos') || count($a['issues']) > 1000) {
            throw new ProcessingException('Appraisal page coverage or extraction limits invalid');
        }foreach (array_merge($a['fields'], $a['photos']) as $f) {
            if (! in_array($f['page'], $pages, true)) {
                throw new ProcessingException('Invalid source page');
            }
        }foreach ($a['issues'] as $issue) {
            if ($issue['page'] !== null && ! in_array($issue['page'], $pages, true)) {
                throw new ProcessingException('Invalid issue source page');
            }
        }
        foreach ($a['fields'] as $f) {
            if (! preg_match('/^[a-z][a-z0-9_.-]{0,99}$/D', $f['key']) || strlen($f['excerpt']) > 4000 || ($f['normalized'] !== null && ! is_string($f['normalized']))) {
                throw new ProcessingException('Invalid appraisal field provenance');
            }
        }

        return $a;
    }

    private static function shape($value, array $schema): void
    {
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            throw new ProcessingException('Unsupported extraction status; provider cannot self-confirm');
        }
        $types = (array) $schema['type'];
        $type = $value === null ? 'null' : (is_bool($value) ? 'boolean' : (is_int($value) ? 'integer' : (is_string($value) ? 'string' : (is_array($value) ? (in_array('object', $types, true) ? 'object' : 'array') : 'invalid'))));
        if (! in_array($type, $types, true) || ($type === 'string' && strlen($value) > 12000)) {
            throw new ProcessingException('Invalid bounded appraisal response shape');
        }
        if ($type === 'object') {
            if (array_diff(array_keys($value), array_keys($schema['properties'])) || array_diff($schema['required'], array_keys($value))) {
                throw new ProcessingException('Unexpected or missing appraisal response properties');
            }
            foreach ($schema['properties'] as $k => $child) {
                self::shape($value[$k], $child);
            }
        } elseif ($type === 'array') {
            if (! array_is_list($value) || count($value) > 2000) {
                throw new ProcessingException('Invalid bounded appraisal response array');
            }
            foreach ($value as $child) {
                self::shape($child, $schema['items']);
            }
        }
    }
}
