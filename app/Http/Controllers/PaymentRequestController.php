<?php

namespace App\Http\Controllers;

use App\Billing\CreditNotifier;
use App\Models\Organization;
use App\Models\PaymentRequest;
use App\Models\Plan;
use App\Services\Access;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Organization admins ask for a plan change or a page pack. Payment is recorded offline by platform staff. */
class PaymentRequestController extends Controller
{
    public function store(Request $r, Organization $organization)
    {
        abort_unless(Access::admin($r->user(), $organization->id), 403, 'Only an organization admin can request a plan change or page top-up.');
        $d = $r->validate([
            'kind' => ['required', Rule::in(['plan', 'pack'])],
            'plan_id' => 'required_if:kind,plan|nullable|integer|exists:plans,id',
            'pack_pages' => 'required_if:kind,pack|nullable|integer|min:1',
            'note' => 'nullable|string|max:1000',
        ]);
        $organization->load('plan');
        $amount = '0.00';
        $packLabel = null;
        $planId = null;
        $packPages = null;
        if ($d['kind'] === 'plan') {
            $plan = Plan::where('is_active', true)->findOrFail($d['plan_id']);
            abort_if((int) $plan->id === (int) $organization->plan_id, 422, 'Your organization is already on this plan.');
            $planId = $plan->id;
            $amount = number_format((float) $plan->provisional_monthly_price, 2, '.', '');
        } else {
            $pack = collect($organization->plan->packs())->first(fn ($pack) => (int) $pack['pages'] === (int) $d['pack_pages']);
            abort_if($pack === null, 422, 'Choose a page pack from your plan.');
            $packPages = (int) $pack['pages'];
            $packLabel = $pack['label'];
            $amount = $pack['price'];
        }

        $request = DB::transaction(function () use ($r, $organization, $d, $amount, $planId, $packPages, $packLabel) {
            Organization::lockForUpdate()->findOrFail($organization->id);
            abort_if(PaymentRequest::where('organization_id', $organization->id)->where('kind', $d['kind'])->where('status', 'pending')->exists(), 422, 'A request of this kind is already waiting for review.');
            $created = PaymentRequest::create([
                'organization_id' => $organization->id,
                'requested_by' => $r->user()->id,
                'kind' => $d['kind'],
                'plan_id' => $planId,
                'pack_pages' => $packPages,
                'pack_label' => $packLabel,
                'amount' => $amount,
                'note' => isset($d['note']) ? trim((string) $d['note']) ?: null : null,
                'status' => 'pending',
            ]);
            Audit::record('payment_request.created', $organization->id, null, ['request_id' => $created->id, 'kind' => $created->kind, 'amount' => $amount]);

            return $created;
        });
        CreditNotifier::paymentRequested($request);

        return response()->json([
            'request' => $request->fresh()->load('plan:id,name'),
            'message' => 'Request sent. Payment is arranged offline, and the team will contact you. Nothing has been charged.',
        ], 201);
    }

    public function cancel(Request $r, Organization $organization, int $paymentRequest)
    {
        abort_unless(Access::admin($r->user(), $organization->id), 403);
        $row = PaymentRequest::where('organization_id', $organization->id)->findOrFail($paymentRequest);
        abort_unless($row->status === 'pending', 409, 'Only a pending request can be cancelled.');
        $row->update(['status' => 'cancelled', 'decided_by' => $r->user()->id, 'decided_at' => now(), 'decision_reason' => 'Cancelled by the organization.']);
        Audit::record('payment_request.cancelled', $organization->id, null, ['request_id' => $row->id]);

        return ['request' => $row->fresh()->load('plan:id,name')];
    }
}
