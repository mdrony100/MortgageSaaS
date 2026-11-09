<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\PlatformRequest;
use App\Services\Access;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Organization owners can ask MortgageLens to change their plan. No payment is taken here. */
class PlanRequestController extends Controller
{
    public function index(Request $r, Organization $organization)
    {
        abort_unless(Access::admin($r->user(), $organization->id), 403);

        return ['requests' => PlatformRequest::where('organization_id', $organization->id)->where('type', 'plan_change')->latest('id')->limit(10)->get(['id', 'status', 'payload', 'decision_reason', 'decided_at', 'created_at'])];
    }

    public function store(Request $r, Organization $organization)
    {
        abort_unless(Access::role($r->user(), $organization->id) === 'owner', 403, 'Only the organization owner can request a plan change.');
        $d = $r->validate(['plan_id' => 'required|integer|exists:plans,id', 'message' => 'nullable|string|max:1000']);
        abort_if((int) $d['plan_id'] === (int) $organization->plan_id, 422, 'Your organization is already on this plan.');

        $req = DB::transaction(function () use ($r, $organization, $d) {
            Organization::lockForUpdate()->findOrFail($organization->id);
            abort_if(PlatformRequest::where('organization_id', $organization->id)->where('type', 'plan_change')->where('status', 'pending')->exists(), 422, 'A plan change request is already waiting for review.');
            $req = PlatformRequest::create(['type' => 'plan_change', 'organization_id' => $organization->id, 'requested_by' => $r->user()->id, 'payload' => ['plan_id' => (int) $d['plan_id'], 'current_plan_id' => $organization->plan_id, 'message' => $d['message'] ?? null]]);
            Audit::record('plan_change.requested', $organization->id, null, ['request_id' => $req->id, 'plan_id' => (int) $d['plan_id']]);

            return $req;
        });

        return response()->json(['request' => $req->only(['id', 'status', 'payload', 'created_at']), 'message' => 'Request sent. MortgageLens will review it; no payment is taken here.'], 201);
    }
}
