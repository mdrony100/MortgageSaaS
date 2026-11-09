<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendPlatformEmail;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlatformRequest;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApprovalController extends Controller
{
    public function index(Request $r)
    {
        $f = $r->validate(['status' => ['nullable', Rule::in(array_merge(array_keys(PlatformRequest::STATUSES), ['all']))], 'type' => ['nullable', Rule::in(array_keys(PlatformRequest::TYPES))], 'page' => 'nullable|integer|min:1']);
        $q = PlatformRequest::with(['organization.plan:id,name', 'requester', 'decider'])->latest('id');
        $status = $f['status'] ?? 'pending';
        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if (! empty($f['type'])) {
            $q->where('type', $f['type']);
        }

        return ['requests' => $q->paginate(20), 'types' => PlatformRequest::TYPES, 'statuses' => PlatformRequest::STATUSES, 'plans' => Plan::orderBy('id')->get(['id', 'name']), 'signup_approval_enabled' => (bool) config('platform.signup_approval')];
    }

    public function decide(Request $r, int $id)
    {
        $d = $r->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'reason' => 'required_if:decision,reject|nullable|string|max:1000']);

        $req = DB::transaction(function () use ($r, $id, $d) {
            $req = PlatformRequest::lockForUpdate()->findOrFail($id);
            abort_unless($req->status === 'pending', 409, 'This request has already been decided.');
            $org = Organization::lockForUpdate()->findOrFail($req->organization_id);
            $approve = $d['decision'] === 'approve';
            $meta = ['request_id' => $req->id, 'type' => $req->type, 'reason' => $d['reason'] ?? null, 'platform_admin_id' => $r->user()->id];
            if ($req->type === 'signup') {
                $org->update(['approval_status' => $approve ? 'approved' : 'rejected', 'approval_reason' => $d['reason'] ?? null, 'approval_decided_at' => now()]);
            } elseif ($approve) {
                $plan = Plan::findOrFail($req->payload['plan_id'] ?? 0);
                $meta['plan'] = ['from' => $org->plan_id, 'to' => $plan->id];
                $org->update(['plan_id' => $plan->id]);
            }
            $req->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $r->user()->id, 'decided_at' => now(), 'decision_reason' => $d['reason'] ?? null]);
            Audit::record('admin.request.'.($approve ? 'approved' : 'rejected'), $org->id, null, $meta);

            return $req;
        });
        $this->notify($req->fresh(['organization']));

        return ['request' => $req->fresh(['organization.plan:id,name', 'requester', 'decider'])];
    }

    private function notify(PlatformRequest $req): void
    {
        $recipients = collect();
        if ($req->requested_by) {
            $recipients->push(User::find($req->requested_by)?->email);
        }
        $owners = Membership::where('organization_id', $req->organization_id)->where('role', 'owner')->with('user:id,email')->get()->pluck('user.email');
        $recipients = $recipients->merge($owners)->filter()->unique();
        $what = $req->type === 'signup' ? 'Your MortgageLens organization "'.$req->organization->name.'"' : 'Your plan change request for "'.$req->organization->name.'"';
        $outcome = $req->status === 'approved' ? 'has been approved.' : 'was not approved.';
        $body = $what.' '.$outcome.($req->decision_reason ? "\n\nNote from MortgageLens: ".$req->decision_reason : '')."\n\nSign in: ".config('app.url').'/login';
        foreach ($recipients as $email) {
            SendPlatformEmail::safely($email, 'MortgageLens: request '.$req->status, $body);
        }
    }
}
