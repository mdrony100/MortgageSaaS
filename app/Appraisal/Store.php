<?php

namespace App\Appraisal;

use App\Bank\FindingLocation;
use App\Bank\Ledger;
use App\Models\Review\Run;
use Illuminate\Support\Facades\DB;

class Store
{
    public static function query(Run $r, string $table = 'review_records')
    {
        return DB::table($table)->where('organization_id', $r->organization_id)->where('loan_file_id', $r->loan_file_id)->where('run_id', $r->id);
    }

    public static function rows(Run $r, ?string $kind = null): array
    {
        return self::query($r)->when($kind, fn ($q) => $q->where('kind', $kind))->orderBy('id')->get()->map(fn ($x) => array_replace((array) $x, ['data' => json_decode($x->data, true)]))->all();
    }

    public static function add(Run $r, string $kind, string $key, array $data): array
    {
        $insert = ['organization_id' => $r->organization_id, 'loan_file_id' => $r->loan_file_id, 'run_id' => $r->id, 'kind' => $kind, 'record_key' => $key, 'data' => Ledger::json($data), 'created_at' => now(), 'updated_at' => now()];
        if ($kind === 'finding') {
            $insert += FindingLocation::columns($data);
        }
        self::query($r)->insertOrIgnore($insert);
        $x = self::query($r)->where('kind', $kind)->where('record_key', $key)->first();

        return array_replace((array) $x, ['data' => json_decode($x->data, true)]);
    }

    public static function latest(Run $r, string $kind): ?array
    {
        $a = self::rows($r, $kind);

        return $a ? end($a) : null;
    }

    public static function facts(Run $r): array
    {
        $out = [];
        foreach (self::rows($r, 'field') as $f) {
            $out[$f['record_key']] = $f;
        }foreach (self::rows($r, 'confirmation') as $c) {
            $key = $c['data']['field_key'];
            if (isset($out[$key])) {
                $out[$key]['confirmation'] = $c['data'];
            }
        }

return array_values($out);
    }

    public static function findings(Run $r): array
    {
        $out = [];
        foreach (self::rows($r, 'finding') as $f) {
            $f['status'] = 'open';
            $f['location'] = FindingLocation::present($f);
            $out[$f['id']] = $f;
        }foreach (self::rows($r, 'finding_review') as $v) {
            if (isset($out[$v['data']['finding_id']])) {
                $out[$v['data']['finding_id']]['status'] = $v['data']['status'];
                $out[$v['data']['finding_id']]['review'] = $v['data'];
            }
        }

return array_values($out);
    }
}
