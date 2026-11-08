<?php

namespace App\Http\Controllers;

use App\Bank\Ledger;
use App\Contracts\SubscriptionBilling;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Access;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function show(Request $q, Organization $organization)
    {
        abort_unless(Access::admin($q->user(), $organization->id), 403);

        return ['subscription' => DB::table('billing_subscriptions')->where('organization_id', $organization->id)->first(), 'current_plan' => $organization->plan, 'provider' => config('billing.provider'), 'plans' => Plan::all()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'file_limit' => $p->file_limit, 'member_limit' => $p->member_limit, 'storage_limit_bytes' => $p->storage_limit_bytes, 'commercial' => config('billing.catalog.'.$p->id), 'pricing_status' => isset(config('billing.catalog')[$p->id]) ? 'client-approved configuration' : 'Awaiting client-approved price; legacy provisional amount not offered']), 'history' => DB::table('billing_events')->where('organization_id', $organization->id)->latest('id')->get()->map(fn ($x) => array_replace((array) $x, ['data' => json_decode($x->data, true)])), 'usage' => DB::table('usage_operations')->where('organization_id', $organization->id)->selectRaw('status,count(*) as operations')->groupBy('status')->get(), 'policies' => ['cancellation' => config('billing.cancellation_policy'), 'refund' => config('billing.refund_policy'), 'count_statuses' => config('billing.count_statuses')], 'notice' => 'No approved live provider selected; browser redirects never grant entitlements.'];
    }

    public function change(Request $q, Organization $organization)
    {
        abort_unless(Access::admin($q->user(), $organization->id), 403);
        $d = $q->validate(['action' => 'required|in:upgrade,downgrade,cancel', 'plan_id' => 'nullable|integer', 'idempotency_key' => 'required|string|max:100']);
        abort_unless(config('billing.provider') === 'fake' && config('billing.fake_enabled') && app()->environment('local', 'testing'), 503, 'Approved billing provider selection/configuration required');
        if ($d['action'] !== 'cancel') {
            abort_unless(isset(config('billing.catalog')[$d['plan_id'] ?? 0]), 422, 'Client-approved plan configuration required');
        }

        return DB::transaction(function () use ($organization, $d) {
            DB::table('organizations')->where('id', $organization->id)->lockForUpdate()->first();
            $old = DB::table('billing_events')->where('organization_id', $organization->id)->where('operation_key', 'request:'.$d['idempotency_key'])->first();
            if ($old) {
                $prior = json_decode($old->data, true);
                abort_unless($prior['action'] === $d['action'] && ($prior['plan_id'] ?? null) === ($d['plan_id'] ?? null), 409, 'Idempotency key already binds another billing request');

                return $prior;
            }$sub = DB::table('billing_subscriptions')->where('organization_id', $organization->id)->first();
            if (! $sub) {
                $ref = (string) Str::uuid();
                DB::table('billing_subscriptions')->insert(['organization_id' => $organization->id, 'provider' => 'fake', 'provider_reference' => $ref, 'status' => 'pending', 'data' => '{}', 'created_at' => now(), 'updated_at' => now()]);
            } else {
                $ref = $sub->provider_reference;
            }$result = app(SubscriptionBilling::class)->checkout(['reference' => $ref, 'action' => $d['action'], 'plan_id' => $d['plan_id'] ?? null]);
            DB::table('billing_events')->insert(['organization_id' => $organization->id, 'operation_key' => 'request:'.$d['idempotency_key'], 'status' => 'requested', 'data' => Ledger::json($result), 'created_at' => now(), 'updated_at' => now()]);
            Audit::record('billing.change_requested', $organization->id);

            return $result;
        });
    }

    public function webhook(Request $q)
    {
        abort_unless(config('billing.provider') === 'fake' && config('billing.fake_enabled') && app()->environment('local', 'testing'), 404);
        $secret = config('billing.webhook_secret');
        $t = $q->header('X-Billing-Timestamp');
        abort_unless($secret && ctype_digit((string) $t) && abs(time() - (int) $t) <= 300 && hash_equals(hash_hmac('sha256', $t.'.'.$q->getContent(), $secret), (string) $q->header('X-Billing-Signature')), 401);
        $d = $q->validate(['event_id' => 'required|string|max:100', 'reference' => 'required|uuid', 'status' => 'required|in:active,past_due,cancelled,refunded,failed', 'request_key' => 'required|string|max:100']);

        return DB::transaction(function () use ($d) {
            $sub = DB::table('billing_subscriptions')->where('provider', 'fake')->where('provider_reference', $d['reference'])->lockForUpdate()->firstOrFail();
            $old = DB::table('billing_events')->where('operation_key', 'event:'.$d['event_id'])->first();
            if ($old) {
                abort_unless($old->organization_id === $sub->organization_id && (json_decode($old->data, true)['payload_hash'] ?? null) === Ledger::hash($d), 409, 'Provider event id is bound to another payload or organization');

                return ['duplicate' => true];
            }$request = DB::table('billing_events')->where('organization_id', $sub->organization_id)->where('operation_key', 'request:'.$d['request_key'])->firstOrFail();
            $requestData = json_decode($request->data, true);
            abort_if(DB::table('billing_events')->where('organization_id', $sub->organization_id)->where('operation_key', 'like', 'request:%')->where('id', '>', $request->id)->exists(), 409, 'Stale provider lifecycle event requires manual reconciliation');
            abort_if($d['status'] === 'cancelled' && $requestData['action'] !== 'cancel', 422, 'Cancellation event must map to cancellation request');
            if ($d['status'] === 'active') {
                abort_unless(in_array($requestData['action'], ['upgrade', 'downgrade'], true) && isset(config('billing.catalog')[$requestData['plan_id']]), 422);
                DB::table('organizations')->where('id', $sub->organization_id)->update(['plan_id' => $requestData['plan_id'], 'subscription_status' => 'active']);
            }$state = $d['status'];
            if ($state === 'cancelled' && config('billing.cancellation_policy') === 'unapproved_preserve_current_access') {
                $state = 'cancellation_requested_policy_pending';
            }DB::table('billing_subscriptions')->where('id', $sub->id)->update(['status' => $state, 'data' => Ledger::json(['last_event' => $d['event_id'], 'notice' => 'No access/retention policy inferred from cancellation or refund']), 'updated_at' => now()]);
            DB::table('billing_events')->insert(['organization_id' => $sub->organization_id, 'operation_key' => 'event:'.$d['event_id'], 'status' => $state, 'data' => Ledger::json(['provider' => 'fake', 'status' => $state, 'request_key' => $d['request_key'], 'payload_hash' => Ledger::hash($d)]), 'created_at' => now(), 'updated_at' => now()]);

            return ['accepted' => true, 'status' => $state];
        });
    }
}
