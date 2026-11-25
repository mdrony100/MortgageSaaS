<?php

namespace App\Http\Controllers\Admin;

use App\Billing\CreditNotifier;
use App\Billing\PageCredits;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PageCreditEntry;
use App\Models\PaymentRequest;
use App\Models\Plan;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentRequestAdminController extends Controller
{
    public function index(Request $r)
    {
        $f = $r->validate([
            'status' => ['nullable', Rule::in(array_merge(array_keys(PaymentRequest::STATUSES), ['all']))],
            'kind' => ['nullable', Rule::in(array_keys(PaymentRequest::KINDS))],
            'page' => 'nullable|integer|min:1',
        ]);
        $q = PaymentRequest::with(['organization.plan:id,name', 'requester', 'decider', 'plan:id,name'])->latest('id');
        $status = $f['status'] ?? 'pending';
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if (! empty($f['kind'])) {
            $q->where('kind', $f['kind']);
        }

        return [
            'requests' => $q->paginate(20),
            'kinds' => PaymentRequest::KINDS,
            'statuses' => PaymentRequest::STATUSES,
        ];
    }

    public function approve(Request $r, int $id)
    {
        $d = $r->validate([
            'payment_method' => 'required|string|max:80',
            'payment_reference' => 'required|string|max:120',
            'payment_note' => 'nullable|string|max:1000',
        ]);
        $row = DB::transaction(function () use ($r, $id, $d) {
            $row = PaymentRequest::lockForUpdate()->findOrFail($id);
            abort_unless($row->status === 'pending', 409, 'This request has already been decided.');
            $org = Organization::lockForUpdate()->findOrFail($row->organization_id);
            if ($row->kind === 'plan') {
                $plan = Plan::where('is_active', true)->findOrFail($row->plan_id);
                $org->update(['plan_id' => $plan->id]);
                app(PageCredits::class)->adoptPlan($org->fresh(), $r->user()->id);
            } else {
                app(PageCredits::class)->credit($org, (int) $row->pack_pages, 'top_up', 'Offline top-up: '.($row->pack_label ?: $row->pack_pages.' pages'), $r->user()->id, 'payment:'.$row->id, $row->id);
            }
            $row->update([
                'status' => 'approved',
                'payment_method' => trim($d['payment_method']),
                'payment_reference' => trim($d['payment_reference']),
                'payment_note' => isset($d['payment_note']) ? trim((string) $d['payment_note']) ?: null : null,
                'paid_offline_at' => now(),
                'decided_by' => $r->user()->id,
                'decided_at' => now(),
            ]);
            Audit::record('payment_request.approved', $org->id, null, [
                'request_id' => $row->id,
                'kind' => $row->kind,
                'payment_method' => $row->payment_method,
                'payment_reference' => $row->payment_reference,
                'platform_admin_id' => $r->user()->id,
            ]);

            return $row;
        });
        CreditNotifier::paymentDecided($row->fresh());

        return ['request' => $row->fresh()->load(['organization.plan:id,name', 'requester', 'decider', 'plan:id,name'])];
    }

    public function reject(Request $r, int $id)
    {
        $d = $r->validate(['reason' => 'required|string|max:1000']);
        $row = DB::transaction(function () use ($r, $id, $d) {
            $row = PaymentRequest::lockForUpdate()->findOrFail($id);
            abort_unless($row->status === 'pending', 409, 'This request has already been decided.');
            $row->update(['status' => 'rejected', 'decision_reason' => trim($d['reason']), 'decided_by' => $r->user()->id, 'decided_at' => now()]);
            Audit::record('payment_request.rejected', $row->organization_id, null, ['request_id' => $row->id, 'reason' => $row->decision_reason, 'platform_admin_id' => $r->user()->id]);

            return $row;
        });
        CreditNotifier::paymentDecided($row->fresh());

        return ['request' => $row->fresh()->load(['organization.plan:id,name', 'requester', 'decider', 'plan:id,name'])];
    }

    public function credits(int $id)
    {
        $org = Organization::with('plan')->findOrFail($id);
        $summary = app(PageCredits::class)->summary($org);

        return $summary + [
            'history' => PageCreditEntry::where('organization_id', $org->id)->with('actor')->latest('id')->limit(40)->get(),
        ];
    }

    public function adjust(Request $r, int $id)
    {
        $d = $r->validate(['pages' => 'required|integer|not_in:0|min:-1000000|max:1000000', 'reason' => 'required|string|max:500']);
        $org = Organization::findOrFail($id);
        $entry = app(PageCredits::class)->adjust($org, (int) $d['pages'], trim($d['reason']), $r->user()->id);

        return ['entry' => $entry, 'credits' => app(PageCredits::class)->summary($org->fresh())];
    }
}
