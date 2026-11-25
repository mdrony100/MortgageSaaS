<?php

namespace App\Http\Controllers\Admin;

use App\Billing\CreditNotifier;
use App\Http\Controllers\Controller;
use App\Models\DemoRequest;
use App\Models\DemoRequestNote;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DemoRequestController extends Controller
{
    public function index(Request $r)
    {
        $f = $r->validate([
            'status' => ['nullable', Rule::in(array_keys(DemoRequest::STATUSES))],
            'spam' => ['nullable', Rule::in(['exclude', 'only', 'all'])],
            'page' => 'nullable|integer|min:1',
        ]);
        $q = DemoRequest::query()->withCount('notes')->latest('id');
        $spam = $f['spam'] ?? 'exclude';
        if ($spam !== 'all') {
            $q->where('is_spam', $spam === 'only');
        }
        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        }

        return [
            'requests' => $q->paginate(25, ['id', 'name', 'email', 'company', 'phone', 'monthly_volume', 'status', 'is_spam', 'spam_reason', 'notification_status', 'created_at']),
            'statuses' => DemoRequest::STATUSES,
            'notification_configured' => CreditNotifier::ownerAdmins() !== [],
        ];
    }

    public function show(int $id)
    {
        return ['request' => DemoRequest::with('notes.author')->findOrFail($id)];
    }

    public function update(Request $r, int $id)
    {
        $row = DemoRequest::findOrFail($id);
        $d = $r->validate([
            'status' => ['sometimes', Rule::in(array_keys(DemoRequest::STATUSES))],
            'is_spam' => 'sometimes|boolean',
        ]);
        abort_if($d === [], 422, 'Nothing to update.');
        if (array_key_exists('is_spam', $d)) {
            $d['spam_reason'] = $d['is_spam'] ? 'staff_marked' : null;
        }
        if (array_key_exists('status', $d)) {
            $d['status_changed_by'] = $r->user()->id;
        }
        $row->update($d);
        Audit::record('demo_request.updated', null, null, ['demo_request_id' => $row->id] + array_intersect_key($d, array_flip(['status', 'is_spam'])));

        return ['request' => $row->fresh()->load('notes.author')];
    }

    public function note(Request $r, int $id)
    {
        $row = DemoRequest::findOrFail($id);
        $d = $r->validate(['body' => 'required|string|max:5000']);
        DemoRequestNote::create(['demo_request_id' => $row->id, 'user_id' => $r->user()->id, 'body' => trim($d['body'])]);
        Audit::record('demo_request.note_added', null, null, ['demo_request_id' => $row->id]);

        return response()->json(['request' => $row->fresh()->load('notes.author')], 201);
    }
}
