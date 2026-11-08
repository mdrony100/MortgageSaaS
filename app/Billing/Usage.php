<?php

namespace App\Billing;

use App\Bank\Ledger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Usage
{
    public function reserve(int $org, string $key): void
    {
        DB::transaction(function () use ($org, $key) {
            DB::table('organizations')->where('id', $org)->lockForUpdate()->first();
            $old = DB::table('usage_operations')->where('organization_id', $org)->where('operation_key', $key)->first();
            if ($old && in_array($old->status, config('billing.count_statuses'), true)) {
                return;
            }
            $limit = config('billing.processing_limit');
            $n = DB::table('usage_operations')->where('organization_id', $org)->whereIn('status', config('billing.count_statuses'))->count();
            if ($limit !== null && $n >= $limit) {
                throw ValidationException::withMessages(['usage' => 'Approved processing limit reached']);
            }if ($old) {
                DB::table('usage_operations')->where('id', $old->id)->update(['status' => 'reserved', 'updated_at' => now()]);

                return;
            }
            DB::table('usage_operations')->insert(['organization_id' => $org, 'operation_key' => $key, 'status' => 'reserved', 'data' => Ledger::json(['policy' => 'count configured status once per pinned operation; no financial charge']), 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function finish(int $org, string $key, string $status): void
    {
        DB::table('usage_operations')->where('organization_id', $org)->where('operation_key', $key)->update(['status' => $status, 'updated_at' => now()]);
    }
}
