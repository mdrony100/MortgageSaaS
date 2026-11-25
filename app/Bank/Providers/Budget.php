<?php

namespace App\Bank\Providers;

use App\Bank\Ledger;
use App\Models\Bank\Run;
use Illuminate\Support\Facades\DB;

class Budget
{
    public static function reserve(Run $r, string $key, string $provider, string $version, int $amount): array
    {
        return DB::transaction(function () use ($r, $key, $provider, $version, $amount) {
            $old = DB::table('bank_provider_calls')->where('organization_id', $r->organization_id)->where('call_key', $key)->first();
            if ($old) {
                return Ledger::decode($old);
            }
            // Page credits are the usage limit. A missing bank_limits row, a zero ceiling,
            // or a zero reservation must not block the call. The amount is recorded only.
            $amount = max(0, $amount);
            $limit = DB::table('bank_limits')->where('organization_id', $r->organization_id)->lockForUpdate()->first();
            if ($limit && $amount > 0) {
                DB::table('bank_limits')->where('organization_id', $r->organization_id)->increment('reserved_micro_usd', $amount);
            }
            $id = DB::table('bank_provider_calls')->insertGetId(Ledger::scope($r) + ['call_key' => $key, 'provider' => $provider, 'version' => $version, 'status' => 'reserved', 'reserved_micro_usd' => $amount, 'data' => '{}', 'created_at' => now(), 'updated_at' => now()]);

            return Ledger::decode(DB::table('bank_provider_calls')->find($id));
        });
    }

    public static function claimSubmission(int $id): bool
    {
        return DB::table('bank_provider_calls')->where('id', $id)->whereIn('status', ['reserved', 'rate_limited'])->update(['status' => 'submitting', 'data' => '{}', 'updated_at' => now()]) === 1;
    }

    public static function update(int $id, string $status, array $data): void
    {
        DB::table('bank_provider_calls')->where('id', $id)->update(['status' => $status, 'data' => Ledger::json($data), 'updated_at' => now()]);
    }
}
