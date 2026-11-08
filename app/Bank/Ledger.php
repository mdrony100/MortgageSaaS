<?php

namespace App\Bank;

use App\Models\Bank\Run;
use Illuminate\Support\Facades\DB;

class Ledger
{
    public static function scope(Run $r): array
    {
        return ['run_id' => $r->id, 'organization_id' => $r->organization_id, 'loan_file_id' => $r->loan_file_id];
    }

    public static function query(string $table, Run $r)
    {
        return DB::table($table)->where(self::scope($r));
    }

    public static function rows(string $table, Run $r): array
    {
        return self::query($table, $r)->orderBy('id')->get()->map(fn ($x) => self::decode($x))->all();
    }

    public static function decode(object $x): array
    {
        $a = (array) $x;
        if (isset($a['data']) && is_string($a['data'])) {
            $a['data'] = json_decode($a['data'], true, 512, JSON_THROW_ON_ERROR);
        }

        return $a;
    }

    public static function append(string $table, Run $r, string $key, array $data): array
    {
        $q = self::query($table, $r)->where('record_key', $key);
        $old = $q->first();
        if ($old) {
            return self::decode($old);
        }
        $id = DB::table($table)->insertGetId(self::scope($r) + ['record_key' => $key, 'data' => self::json($data), 'created_at' => now()]);

        return self::decode(DB::table($table)->find($id));
    }

    public static function json(mixed $x): string
    {
        return json_encode($x, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    public static function hash(mixed $x): string
    {
        return hash('sha256', self::json(self::canonical($x)));
    }

    private static function canonical(mixed $x): mixed
    {
        if (! is_array($x)) {
            return $x;
        }
        if (! array_is_list($x)) {
            ksort($x, SORT_STRING);
        }
        foreach ($x as &$value) {
            $value = self::canonical($value);
        }unset($value);

        return $x;
    }

    public static function redact(string $s): string
    {
        return preg_replace_callback('/(?<!\d)\d(?:[ \t-]?\d){7,}(?!\d)/', fn ($m) => preg_match('/^\d{4}-\d{2}-\d{2}$/D', $m[0]) ? $m[0] : '****'.substr(preg_replace('/\D/', '', $m[0]), -4), mb_substr($s, 0, 4000));
    }

    public static function latestDecisions(string $table, Run $r, string $field): array
    {
        $out = [];
        foreach (self::rows($table, $r) as $row) {
            $out[$row['data'][$field]] = $row;
        }

        return $out;
    }
}
