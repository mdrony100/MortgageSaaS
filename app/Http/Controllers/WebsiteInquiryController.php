<?php

namespace App\Http\Controllers;

use App\Models\WebsiteInquiry;
use App\Models\WebsiteInquiryNote;
use App\Services\Audit;
use Illuminate\Http\Request;
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
            'inquiries' => $q->paginate(25, ['id', 'name', 'email', 'company', 'role', 'inquiry_type', 'status', 'is_spam', 'spam_reason', 'notification_status', 'created_at']),
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
}
