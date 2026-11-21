<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Services\Access;
use App\Services\Audit;
use App\Services\SupportDesk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index(Request $request, Organization $organization)
    {
        abort_unless(Access::membership($request->user(), $organization->id), 403);
        $tickets = SupportTicket::query()->with(['organization:id,name', 'user:id,name'])->where('organization_id', $organization->id)->when(! Access::admin($request->user(), $organization->id), fn ($query) => $query->where('user_id', $request->user()->id))->latest('updated_at')->paginate(20)->through(fn (SupportTicket $ticket) => SupportDesk::summary($ticket));

        return ['tickets' => $tickets, 'categories' => SupportTicket::CATEGORIES, 'priorities' => SupportTicket::PRIORITIES, 'statuses' => SupportTicket::STATUSES];
    }

    public function store(Request $request, Organization $organization)
    {
        abort_unless(Access::membership($request->user(), $organization->id), 403);
        $data = $this->validated($request);
        $ticket = DB::transaction(function () use ($request, $organization, $data) {
            $ticket = SupportTicket::create(['organization_id' => $organization->id, 'user_id' => $request->user()->id, 'subject' => trim($data['subject']), 'category' => $data['category'], 'priority' => $data['priority'], 'status' => 'open']);
            SupportDesk::storeMessage($request, $ticket, $request->user(), false, $data['message']);
            Audit::record('support_ticket.created', $organization->id, null, ['ticket_id' => $ticket->id, 'category' => $ticket->category, 'priority' => $ticket->priority]);

            return $ticket;
        });
        SupportDesk::notify(SupportDesk::staffEmails($request->user()->email), 'Support ticket opened: '.$ticket->subject, "A support ticket was opened for {$organization->name}.\n\nSubject: {$ticket->subject}\nCategory: {$ticket->category}\nPriority: {$ticket->priority}\nTicket #{$ticket->id}\n\nSign in to the owner console to reply. The message is not included in this email.");

        return response()->json(['ticket' => SupportDesk::detail($ticket->fresh())], 201);
    }

    public function show(Request $request, Organization $organization, SupportTicket $ticket)
    {
        $this->authorizeTicket($request, $organization, $ticket);

        return ['ticket' => SupportDesk::detail($ticket)];
    }

    public function reply(Request $request, Organization $organization, SupportTicket $ticket)
    {
        $this->authorizeTicket($request, $organization, $ticket);
        $data = $request->validate(['message' => 'required|string|max:5000', 'file' => 'nullable|file|max:20480']);
        DB::transaction(function () use ($request, $ticket, $data) {
            SupportDesk::storeMessage($request, $ticket, $request->user(), false, $data['message']);
            if (in_array($ticket->status, ['resolved', 'closed'], true)) {
                $ticket->update(['status' => 'open']);
            }
            Audit::record('support_ticket.replied', $ticket->organization_id, null, ['ticket_id' => $ticket->id, 'staff' => false]);
        });
        $ticket->refresh();
        SupportDesk::notify(SupportDesk::staffEmails($request->user()->email), 'Support ticket reply: '.$ticket->subject, "{$request->user()->name} replied on ticket #{$ticket->id} ({$ticket->subject}) for {$ticket->organization->name}.\n\nSign in to the owner console to read it.");

        return response()->json(['ticket' => SupportDesk::detail($ticket)], 201);
    }

    public function content(Request $request, SupportTicketAttachment $attachment)
    {
        $message = $attachment->message()->firstOrFail();
        $ticket = SupportTicket::findOrFail($message->support_ticket_id);
        abort_unless($attachment->organization_id === $ticket->organization_id && $message->organization_id === $ticket->organization_id, 404);
        abort_unless(SupportDesk::canSee($request->user(), $ticket), 403);
        abort_unless(SupportDesk::readable($attachment), 423, 'This file is quarantined and cannot be opened.');
        Audit::record('support_ticket.attachment_opened', $ticket->organization_id, null, ['ticket_id' => $ticket->id, 'attachment_id' => $attachment->id]);

        return SupportDesk::download($attachment);
    }

    private function authorizeTicket(Request $request, Organization $organization, SupportTicket $ticket): void
    {
        abort_unless($ticket->organization_id === $organization->id, 404);
        abort_unless(Access::membership($request->user(), $organization->id), 403);
        abort_unless(SupportDesk::canSee($request->user(), $ticket), 403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'subject' => 'required|string|max:160',
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'message' => 'required|string|max:5000',
            'file' => 'nullable|file|max:20480',
        ]);
    }
}
