<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\DocumentVersion;
use App\Models\Invitation;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Services\Access;
use App\Services\Audit;
use App\Services\Entitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $r)
    {
        return $r->user()->memberships()->with('organization.plan')->get();
    }

    public function show(Request $r, Organization $organization)
    {
        abort_unless(Access::membership($r->user(), $organization->id), 403);

        return ['organization' => $organization->load('plan'), 'role' => Access::role($r->user(), $organization->id), 'members' => Access::admin($r->user(), $organization->id) ? Membership::where('organization_id', $organization->id)->with('user:id,name,email')->get() : [], 'invitations' => Access::admin($r->user(), $organization->id) ? Invitation::where('organization_id', $organization->id)->get(['id', 'email', 'role', 'expires_at', 'accepted_at', 'revoked_at']) : []];
    }

    public function update(Request $r, Organization $organization)
    {
        abort_unless(Access::admin($r->user(), $organization->id), 403);
        $d = $r->validate(['name' => 'required|string|max:120', 'branding' => 'nullable|array:product_name,accent,logo_text', 'branding.product_name' => 'nullable|string|max:40', 'branding.accent' => 'nullable|regex:/^#[a-fA-F0-9]{6}$/', 'branding.logo_text' => 'nullable|string|max:20', 'preferences' => 'nullable|array:timezone,currency', 'preferences.timezone' => 'nullable|timezone', 'preferences.currency' => ['nullable', Rule::in(['USD'])]]);
        $organization->update($d);
        Audit::record('organization.updated', $organization->id);

        return $organization;
    }

    public function invite(Request $r, Organization $organization)
    {
        abort_unless(Access::admin($r->user(), $organization->id), 403);
        $d = $r->validate(['email' => 'required|email|max:254', 'role' => ['required', Rule::in(['admin', 'underwriter', 'processor', 'broker', 'viewer'])], 'org_wide_access' => 'sometimes|boolean']);
        $d['email'] = strtolower($d['email']);
        abort_if(($d['org_wide_access'] ?? false) && $d['role'] !== 'admin', 422, 'Organization-wide grants require the admin role.');
        $token = Str::random(64);
        $invite = DB::transaction(function () use ($r, $organization, $d, $token) {
            $org = Organization::lockForUpdate()->findOrFail($organization->id);
            Entitlements::members($org);
            abort_if(Membership::where('organization_id', $org->id)->whereHas('user', fn ($q) => $q->where('email', $d['email']))->exists(), 422, 'Already a member.');
            abort_if(Invitation::where('organization_id', $org->id)->where('email', $d['email'])->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists(), 422, 'Invitation pending.');

            return Invitation::create($d + ['id' => (string) Str::uuid(), 'organization_id' => $org->id, 'token_hash' => hash('sha256', $token), 'invited_by' => $r->user()->id, 'expires_at' => now()->addDays(7)]);
        });
        $url = config('app.url').'/invitations/accept?token='.$token;
        Mail::raw('You are invited to a MortgageLens workspace. Sign in with this email, then open: '.$url, fn ($m) => $m->to($d['email'])->subject('MortgageLens workspace invitation'));
        Audit::record('invitation.created', $organization->id);

        return response()->json(['id' => $invite->id, 'message' => 'Invitation sent to configured mail transport.'], 201);
    }

    public function accept(Request $r)
    {
        $d = $r->validate(['token' => 'required|string|max:100']);

        return DB::transaction(function () use ($r, $d) {
            $i = Invitation::where('token_hash', hash('sha256', $d['token']))->lockForUpdate()->firstOrFail();
            abort_if($i->accepted_at || $i->revoked_at || $i->expires_at->isPast(), 410);
            abort_unless(hash_equals($i->email, strtolower($r->user()->email)), 403);
            $org = Organization::lockForUpdate()->findOrFail($i->organization_id);
            abort_if(Membership::where('organization_id', $org->id)->count() >= $org->memberLimit(), 422, 'Member limit reached.');
            $m = Membership::firstOrCreate(['organization_id' => $i->organization_id, 'user_id' => $r->user()->id], ['role' => $i->role, 'org_wide_access' => $i->org_wide_access]);
            $i->update(['accepted_at' => now()]);
            Audit::record('invitation.accepted', $org->id);

            return $m;
        });
    }

    public function revokeInvite(Request $r, Organization $organization, Invitation $invitation)
    {
        abort_unless(Access::admin($r->user(), $organization->id), 403);
        abort_unless($invitation->organization_id === $organization->id, 404);
        $invitation->update(['revoked_at' => now()]);
        Audit::record('invitation.revoked', $organization->id);

        return response()->noContent();
    }

    public function member(Request $r, Organization $organization, Membership $membership)
    {
        abort_unless(Access::admin($r->user(), $organization->id), 403);
        abort_unless($membership->organization_id === $organization->id, 404);
        abort_if($membership->role === 'owner' || $membership->user_id === $r->user()->id, 422, 'Cannot change owner or own membership.');
        $d = $r->validate(['role' => ['required', Rule::in(['admin', 'underwriter', 'processor', 'broker', 'viewer'])], 'org_wide_access' => 'required|boolean']);
        abort_if($d['org_wide_access'] && $d['role'] !== 'admin', 422);
        $membership->update($d);
        Audit::record('membership.updated', $organization->id, null, ['member_id' => $membership->id]);

        return $membership;
    }

    public function removeMember(Request $r, Organization $organization, Membership $membership)
    {
        abort_unless(Access::admin($r->user(), $organization->id), 403);
        abort_unless($membership->organization_id === $organization->id, 404);
        abort_if($membership->role === 'owner' || $membership->user_id === $r->user()->id, 422);
        DB::transaction(function () use ($membership) {
            DB::table('file_shares')->where('organization_id', $membership->organization_id)->where('user_id', $membership->user_id)->delete();
            $membership->delete();
        });
        Audit::record('membership.removed', $organization->id);

        return response()->noContent();
    }

    public function audit(Request $r, Organization $organization)
    {
        abort_unless(Access::admin($r->user(), $organization->id), 403);
        $files = Access::visible($r->user())->where('organization_id', $organization->id)->pluck('id');

        return AuditEvent::where('organization_id', $organization->id)->where(fn ($q) => $q->whereNull('loan_file_id')->orWhereIn('loan_file_id', $files))->latest('id')->limit(200)->get(['id', 'loan_file_id', 'actor_id', 'action', 'created_at']);
    }

    public function usage(Request $r, Organization $organization)
    {
        abort_unless(Access::admin($r->user(), $organization->id), 403);

        return ['files' => LoanFile::where('organization_id', $organization->id)->count(), 'members' => Membership::where('organization_id', $organization->id)->count(), 'pending_invitations' => Invitation::where('organization_id', $organization->id)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count(), 'storage_bytes' => (int) DocumentVersion::where('organization_id', $organization->id)->sum('bytes'), 'limits' => ['files' => $organization->fileLimit(), 'members' => $organization->memberLimit(), 'storage_bytes' => $organization->storageLimitBytes()]];
    }

    public function plans()
    {
        return ['plans' => Plan::all()->makeHidden('provisional_monthly_price'), 'pricing_notice' => 'Client-approved prices and live provider selection pending; legacy sample prices are not offered.'];
    }
}
