<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\Audit;
use App\Services\SupportDesk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))],
            'q' => 'nullable|string|max:120',
            'page' => 'nullable|integer|min:1',
        ]);
        $query = SupportTicket::query()->with(['organization:id,name', 'user:id,name'])->latest('updated_at');
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['q'])) {
            $term = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function ($inner) use ($term) {
                $inner->where('subject', 'ilike', $term)->orWhereHas('organization', fn ($org) => $org->where('name', 'ilike', $term));
            });
        }

        return [
            'tickets' => $query->paginate(25)->through(fn (SupportTicket $ticket) => SupportDesk::summary($ticket)),
            'statuses' => SupportTicket::STATUSES,
            'categories' => SupportTicket::CATEGORIES,
            'priorities' => SupportTicket::PRIORITIES,
        ];
    }

    public function show(int $id)
    {
        return ['ticket' => SupportDesk::detail(SupportTicket::findOrFail($id))];
    }

    public function update(Request $request, int $id)
    {
        $ticket = SupportTicket::findOrFail($id);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))]]);
        $from = $ticket->status;
        if ($from !== $data['status']) {
            $ticket->update(['status' => $data['status']]);
            Audit::record('support_ticket.status_changed', $ticket->organization_id, null, ['ticket_id' => $ticket->id, 'from' => $from, 'to' => $data['status']]);
            $creator = $ticket->user;
            if ($creator) {
                SupportDesk::notify([$creator->email], 'Support ticket update: '.$ticket->subject, "Ticket #{$ticket->id} ({$ticket->subject}) is now {$data['status']}.\n\nSign in to your workspace and open Support tickets to read the thread.\n\n".rtrim((string) config('app.url'), '/').'/support/'.$ticket->id, 'customer_support');
            }
        }

        return ['ticket' => SupportDesk::detail($ticket->fresh())];
    }

    public function reply(Request $request, int $id)
    {
        $ticket = SupportTicket::findOrFail($id);
        $data = $request->validate(['message' => 'required|string|max:5000', 'file' => 'nullable|file|max:20480']);
        DB::transaction(function () use ($request, $ticket, $data) {
            SupportDesk::storeMessage($request, $ticket, $request->user(), true, $data['message']);
            Audit::record('support_ticket.replied', $ticket->organization_id, null, ['ticket_id' => $ticket->id, 'staff' => true]);
        });
        $creator = $ticket->user;
        if ($creator && strcasecmp($creator->email, $request->user()->email) !== 0) {
            SupportDesk::notify([$creator->email], 'Support ticket reply: '.$ticket->subject, "Non-QM Doc AI replied on ticket #{$ticket->id} ({$ticket->subject}).\n\nSign in to your workspace and open Support tickets to read the reply.\n\n".rtrim((string) config('app.url'), '/').'/support/'.$ticket->id, 'customer_support');
        }

        return response()->json(['ticket' => SupportDesk::detail($ticket->fresh())], 201);
    }

    public function content(SupportTicketAttachment $attachment)
    {
        $message = $attachment->message()->firstOrFail();
        $ticket = SupportTicket::findOrFail($message->support_ticket_id);
        abort_unless($attachment->organization_id === $ticket->organization_id && $message->organization_id === $ticket->organization_id, 404);
        abort_unless(SupportDesk::readable($attachment), 423, 'This file is quarantined and cannot be opened.');
        Audit::record('support_ticket.attachment_opened', $ticket->organization_id, null, ['ticket_id' => $ticket->id, 'attachment_id' => $attachment->id, 'staff' => true]);

        return SupportDesk::download($attachment);
    }
}
