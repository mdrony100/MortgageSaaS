<?php

namespace App\Http\Controllers;

use App\Jobs\SendPlatformEmail;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\WebsiteInquiry;
use App\Models\WebsiteInquiryNote;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Platform-staff-only management of public website inquiries.
 * Organization roles (owner/admin/...) never grant access; only users.platform_admin does.
 */
class WebsiteInquiryController extends Controller
{
    private function staff(Request $r): void
    {
        abort_unless($r->user()?->platform_admin === true, 403, 'Platform staff only.');
    }

    public function index(Request $r)
    {
        $this->staff($r);
        $f = $r->validate([
            'type' => ['nullable', Rule::in(array_keys(config('website.inquiry_types')))],
            'status' => ['nullable', Rule::in(array_keys(WebsiteInquiry::STATUSES))],
            'spam' => ['nullable', Rule::in(['exclude', 'only', 'all'])],
            'page' => 'nullable|integer|min:1',
        ]);
        $q = WebsiteInquiry::query()->withCount('notes')->latest('id');
        $spam = $f['spam'] ?? 'exclude';
        if ($spam !== 'all') {
            $q->where('is_spam', $spam === 'only');
        }
        if (! empty($f['type'])) {
            $q->where('inquiry_type', $f['type']);
        }
        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        }

        return [
            'inquiries' => $q->paginate(25, ['id', 'name', 'email', 'company', 'role', 'inquiry_type', 'status', 'is_spam', 'spam_reason', 'notification_status', 'converted_organization_id', 'converted_at', 'created_at']),
            'plans' => Plan::orderBy('id')->get(['id', 'name']),
            'types' => config('website.inquiry_types'),
            'statuses' => WebsiteInquiry::STATUSES,
            'notification_configured' => filled(config('website.inquiry_notify_email')),
        ];
    }

    public function show(Request $r, int $id)
    {
        $this->staff($r);
        $inquiry = WebsiteInquiry::findOrFail($id);

        return ['inquiry' => $inquiry->load('notes.author')];
    }

    public function update(Request $r, int $id)
    {
        $this->staff($r);
        $inquiry = WebsiteInquiry::findOrFail($id);
        $d = $r->validate(['status' => ['sometimes', Rule::in(array_keys(WebsiteInquiry::STATUSES))], 'is_spam' => 'sometimes|boolean']);
        abort_if($d === [], 422, 'Nothing to update.');
        if (array_key_exists('is_spam', $d)) {
            $d['spam_reason'] = $d['is_spam'] ? 'staff_marked' : null;
        }
        $inquiry->update($d + ['status_changed_by' => $r->user()->id]);
        Audit::record('website_inquiry.updated', null, null, ['inquiry_id' => $inquiry->id] + array_intersect_key($d, array_flip(['status', 'is_spam'])));

        return ['inquiry' => $inquiry->fresh()->load('notes.author')];
    }

    public function note(Request $r, int $id)
    {
        $this->staff($r);
        $inquiry = WebsiteInquiry::findOrFail($id);
        $d = $r->validate(['body' => 'required|string|max:5000']);
        WebsiteInquiryNote::create(['website_inquiry_id' => $inquiry->id, 'user_id' => $r->user()->id, 'body' => trim($d['body'])]);
        Audit::record('website_inquiry.note_added', null, null, ['inquiry_id' => $inquiry->id]);

        return response()->json(['inquiry' => $inquiry->fresh()->load('notes.author')], 201);
    }

    /**
     * Turn a genuine demo/contact inquiry into a new organization plus an owner invitation,
     * using the existing invitation flow (hashed token, 7-day expiry, accept while signed in).
     */
    public function convert(Request $r, int $id)
    {
        $this->staff($r);
        $d = $r->validate(['organization_name' => 'required|string|max:120', 'plan_id' => 'required|integer|exists:plans,id', 'trial_days' => 'nullable|integer|min:0|max:365']);
        $token = Str::random(64);
        [$inquiry, $org] = DB::transaction(function () use ($r, $id, $d, $token) {
            $inquiry = WebsiteInquiry::lockForUpdate()->findOrFail($id);
            abort_if($inquiry->is_spam, 422, 'Mark this inquiry as not spam before converting it.');
            abort_if($inquiry->converted_at !== null, 409, 'This inquiry has already been converted.');
            $email = strtolower($inquiry->email);
            abort_if(User::where('email', $email)->where('platform_admin', true)->exists(), 422, 'This email belongs to platform staff.');
            $trial = (int) ($d['trial_days'] ?? 0);
            $org = Organization::create(['name' => $d['organization_name'], 'plan_id' => $d['plan_id'], 'subscription_status' => 'trial', 'trial_ends_at' => $trial > 0 ? now()->addDays($trial) : null, 'approval_status' => 'approved', 'approval_decided_at' => now(), 'branding' => ['product_name' => config('app.name'), 'accent' => '#0f766e'], 'preferences' => ['timezone' => 'America/New_York', 'currency' => 'USD']]);
            Invitation::create(['id' => (string) Str::uuid(), 'organization_id' => $org->id, 'email' => $email, 'role' => 'owner', 'org_wide_access' => true, 'token_hash' => hash('sha256', $token), 'invited_by' => $r->user()->id, 'expires_at' => now()->addDays(7)]);
            $inquiry->update(['converted_organization_id' => $org->id, 'converted_at' => now(), 'status' => $inquiry->status === 'new' ? 'in_progress' : $inquiry->status, 'status_changed_by' => $r->user()->id]);
            Audit::record('website_inquiry.converted', $org->id, null, ['inquiry_id' => $inquiry->id, 'organization_id' => $org->id, 'platform_admin_id' => $r->user()->id]);

            return [$inquiry, $org];
        });
        $url = config('app.url').'/invitations/accept?token='.$token;
        $queued = SendPlatformEmail::safely($inquiry->email, 'Your MortgageLens workspace invitation', "Hello {$inquiry->name},\n\nThank you for your interest in MortgageLens. A workspace named \"{$org->name}\" has been created for you as the owner.\n\nCreate an account or sign in with this email address ({$inquiry->email}), then open this link within 7 days:\n{$url}\n");

        return response()->json(['inquiry' => $inquiry->fresh()->load('notes.author'), 'organization_id' => $org->id, 'message' => $queued ? 'Organization created and owner invitation sent.' : 'Organization and invitation created, but the email could not be queued. Check mail settings.'], 201);
    }
}
