<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationAdminNote;
use App\Models\Plan;
use App\Models\PlatformRequest;
use App\Services\Audit;
use App\Services\Entitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrganizationAdminController extends Controller
{
    public function index(Request $r)
    {
        $f = $r->validate(['q' => 'nullable|string|max:120', 'status' => ['nullable', Rule::in(array_merge(array_keys(Organization::STATUSES), ['suspended_now', 'pending_approval']))], 'plan_id' => 'nullable|integer', 'page' => 'nullable|integer|min:1']);
        $q = Organization::query()->with('plan:id,name')->withCount(['memberships as member_count'])->addSelect(['file_count' => LoanFile::selectRaw('count(*)')->whereColumn('organization_id', 'organizations.id'), 'upload_bytes' => DB::table('document_versions')->selectRaw('COALESCE(SUM(bytes),0)')->whereColumn('organization_id', 'organizations.id')])->latest('id');
        if (! empty($f['q'])) {
            $q->where('name', 'ilike', '%'.addcslashes($f['q'], '%_\\').'%');
        }
        if (($f['status'] ?? null) === 'suspended_now') {
            $q->whereNotNull('suspended_at');
        } elseif (($f['status'] ?? null) === 'pending_approval') {
            $q->where('approval_status', 'pending');
        } elseif (! empty($f['status'])) {
            $q->where('subscription_status', $f['status']);
        }
        if (! empty($f['plan_id'])) {
            $q->where('plan_id', $f['plan_id']);
        }

        return ['organizations' => $q->paginate(20, ['organizations.id', 'name', 'plan_id', 'subscription_status', 'approval_status', 'suspended_at', 'trial_ends_at', 'created_at']), 'statuses' => Organization::STATUSES, 'plans' => Plan::orderBy('id')->get(['id', 'name'])];
    }

    public function show(int $id)
    {
        $org = Organization::with('plan')->findOrFail($id)->makeVisible(['suspension_reason', 'approval_reason']);

        return $this->detail($org);
    }

    private function detail(Organization $org): array
    {
        $members = Membership::where('organization_id', $org->id)->with('user:id,name,email,email_verified_at,disabled_at,platform_admin')->orderBy('id')->get(['id', 'user_id', 'role', 'org_wide_access', 'created_at']);

        return [
            'organization' => collect($org->only(['id', 'name', 'subscription_status', 'approval_status', 'approval_reason', 'approval_decided_at', 'suspended_at', 'suspension_reason', 'trial_ends_at', 'file_limit_override', 'member_limit_override', 'storage_limit_bytes_override', 'created_at']))->merge(['plan' => $org->plan->only(['id', 'name', 'file_limit', 'member_limit', 'storage_limit_bytes'])]),
            'limits' => ['files' => $org->fileLimit(), 'members' => $org->memberLimit(), 'storage_bytes' => $org->storageLimitBytes()],
            'usage' => [
                'files' => LoanFile::where('organization_id', $org->id)->count(),
                'files_by_module' => LoanFile::where('organization_id', $org->id)->selectRaw('module, count(*) as n')->groupBy('module')->pluck('n', 'module'),
                'members' => $members->count(),
                'pending_invitations' => Invitation::where('organization_id', $org->id)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count(),
                'storage_bytes' => Entitlements::storageUsed($org->id),
            ],
            'members' => $members,
            'subscription' => DB::table('billing_subscriptions')->where('organization_id', $org->id)->first(['provider', 'status', 'created_at', 'updated_at']),
            'requests' => PlatformRequest::where('organization_id', $org->id)->with('requester', 'decider')->latest('id')->limit(10)->get(),
            'notes' => OrganizationAdminNote::where('organization_id', $org->id)->with('author')->orderByDesc('id')->get(),
            'history' => AuditEvent::where('organization_id', $org->id)->whereNull('loan_file_id')->where(fn ($q) => $q->where('action', 'like', 'admin.%')->orWhere('action', 'like', 'platform_%'))->latest('id')->limit(25)->get(['id', 'action', 'actor_id', 'metadata', 'created_at']),
            'statuses' => Organization::STATUSES,
            'plans' => Plan::orderBy('id')->get(['id', 'name', 'file_limit', 'member_limit', 'storage_limit_bytes']),
        ];
    }

    public function action(Request $r, int $id)
    {
        $d = $r->validate([
            'action' => ['required', Rule::in(['change_plan', 'set_status', 'suspend', 'reactivate', 'extend_trial', 'end_trial', 'set_limits'])],
            'plan_id' => 'required_if:action,change_plan|nullable|integer|exists:plans,id',
            'status' => ['nullable', Rule::in(array_keys(Organization::STATUSES))],
            'reason' => 'nullable|string|max:500',
            'days' => 'required_if:action,extend_trial|nullable|integer|min:1|max:365',
            'file_limit' => 'nullable|integer|min:0|max:1000000',
            'member_limit' => 'nullable|integer|min:0|max:100000',
            'storage_gb' => 'nullable|numeric|min:0|max:100000',
        ]);
        $action = $d['action'];
        if ($action === 'set_status') {
            abort_if(empty($d['status']), 422, 'Choose a status.');
        }
        if ($action === 'suspend' || ($action === 'set_status' && $d['status'] === 'suspended')) {
            abort_if(blank($d['reason'] ?? null), 422, 'A reason is required to suspend an organization.');
        }

        return DB::transaction(function () use ($r, $id, $d, $action) {
            $org = Organization::lockForUpdate()->findOrFail($id);
            $before = $org->only(['plan_id', 'subscription_status', 'suspended_at', 'trial_ends_at', 'file_limit_override', 'member_limit_override', 'storage_limit_bytes_override']);
            switch ($action) {
                case 'change_plan':
                    $org->plan_id = $d['plan_id'];
                    break;
                case 'suspend':
                    $d['status'] = 'suspended';
                    // no break
                case 'set_status':
                    $org->subscription_status = $d['status'];
                    if ($d['status'] === 'suspended') {
                        $org->suspended_at = $org->suspended_at ?? now();
                        $org->suspension_reason = $d['reason'];
                    } else {
                        $org->suspended_at = null;
                        $org->suspension_reason = null;
                    }
                    break;
                case 'reactivate':
                    $status = $d['status'] ?? 'active';
                    abort_if($status === 'suspended', 422, 'Choose the status to restore.');
                    $org->subscription_status = $status;
                    $org->suspended_at = null;
                    $org->suspension_reason = null;
                    break;
                case 'extend_trial':
                    $base = $org->trial_ends_at && $org->trial_ends_at->isFuture() ? $org->trial_ends_at : now();
                    $org->trial_ends_at = $base->copy()->addDays((int) $d['days']);
                    $org->subscription_status = 'trial';
                    $org->suspended_at = null;
                    break;
                case 'end_trial':
                    $status = $d['status'] ?? 'active';
                    abort_unless(in_array($status, ['active', 'past_due', 'cancelled'], true), 422, 'After a trial the status must be active, past due or cancelled.');
                    $org->trial_ends_at = now();
                    $org->subscription_status = $status;
                    break;
                case 'set_limits':
                    $org->file_limit_override = $d['file_limit'] ?? null;
                    $org->member_limit_override = $d['member_limit'] ?? null;
                    $org->storage_limit_bytes_override = isset($d['storage_gb']) ? (int) round($d['storage_gb'] * 1073741824) : null;
                    break;
            }
            $org->save();
            $after = $org->only(array_keys($before));
            $changes = [];
            foreach ($after as $k => $v) {
                $old = $before[$k] instanceof \DateTimeInterface ? $before[$k]->format(DATE_ATOM) : $before[$k];
                $new = $v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : $v;
                if ($old !== $new) {
                    $changes[$k] = ['from' => $old, 'to' => $new];
                }
            }
            Audit::record('admin.organization.'.$action, $org->id, null, ['changes' => $changes, 'reason' => $d['reason'] ?? null, 'platform_admin_id' => $r->user()->id]);

            return $this->detail($org->fresh('plan')->makeVisible(['suspension_reason', 'approval_reason']));
        });
    }

    public function note(Request $r, int $id)
    {
        $org = Organization::findOrFail($id);
        $d = $r->validate(['body' => 'required|string|max:5000']);
        $note = OrganizationAdminNote::create(['organization_id' => $org->id, 'user_id' => $r->user()->id, 'body' => trim($d['body'])]);
        Audit::record('admin.organization.note_added', null, null, ['organization_id' => $org->id, 'note_id' => $note->id, 'platform_admin_id' => $r->user()->id]);

        return response()->json(['notes' => OrganizationAdminNote::where('organization_id', $org->id)->with('author')->orderByDesc('id')->get()], 201);
    }
}
