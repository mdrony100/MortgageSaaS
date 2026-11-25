<?php

namespace App\Services;

use App\Bank\Ledger;
use App\Bank\Scanner;
use App\Jobs\SendPlatformEmail;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportDesk
{
    public static function canSee(User $user, SupportTicket $ticket): bool
    {
        if (! Access::membership($user, $ticket->organization_id)) {
            return false;
        }

        return Access::admin($user, $ticket->organization_id) || $ticket->user_id === $user->id;
    }

    public static function summary(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'organization_id' => $ticket->organization_id,
            'organization' => $ticket->organization?->name,
            'requester' => $ticket->user?->name,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ];
    }

    public static function detail(SupportTicket $ticket): array
    {
        $ticket->loadMissing(['organization:id,name', 'user:id,name', 'messages.user:id,name', 'messages.attachments']);

        return self::summary($ticket) + [
            'messages' => $ticket->messages->map(fn (SupportTicketMessage $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'staff' => $message->staff,
                'author' => $message->user?->name,
                'created_at' => $message->created_at,
                'attachments' => $message->attachments->map(fn (SupportTicketAttachment $file) => [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'mime' => $file->mime,
                    'bytes' => $file->bytes,
                    'scan_status' => $file->scan_status,
                ])->values(),
            ])->values(),
        ];
    }

    /** @param  list<string|null>  $emails */
    public static function notify(array $emails, string $subject, string $body, ?string $group = null): void
    {
        if ($group && ! NotificationMail::enabled($group)) {
            return;
        }
        foreach (array_unique(array_filter($emails)) as $to) {
            try {
                dispatch(new SendPlatformEmail($to, $subject, $body, $group));
            } catch (\Throwable $e) {
                Log::warning('Support ticket email failed', ['exception_type' => $e::class]);
            }
        }
    }

    /** @return list<string> */
    public static function staffEmails(?string $except = null): array
    {
        $configured = config('website.inquiry_notify_email');
        $list = filled($configured)
            ? [trim((string) $configured)]
            : User::query()->where('platform_admin', true)->whereNotNull('email')->pluck('email')->all();

        return array_values(array_filter($list, fn ($email) => is_string($email) && $email !== '' && ($except === null || strcasecmp($email, $except) !== 0)));
    }

    public static function storeMessage(Request $request, SupportTicket $ticket, User $author, bool $staff, string $body): SupportTicketMessage
    {
        $message = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'organization_id' => $ticket->organization_id,
            'user_id' => $author->id,
            'staff' => $staff,
            'body' => trim($body),
        ]);
        self::attach($request, $message);
        $ticket->touch();

        return $message;
    }

    public static function attach(Request $request, SupportTicketMessage $message): ?SupportTicketAttachment
    {
        if (! $request->hasFile('file')) {
            return null;
        }
        $upload = $request->file('file');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload->getRealPath());
        $ext = strtolower($upload->getClientOriginalExtension());
        $allowed = ['pdf' => ['application/pdf'], 'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg']];
        if (! isset($allowed[$ext]) || ! in_array($mime, $allowed[$ext], true)) {
            throw ValidationException::withMessages(['file' => 'File content and extension must agree (PDF, PNG, JPG).']);
        }
        $scanner = app(Scanner::class);
        $scan = $scanner->installed()
            ? $scanner->scan($upload->getRealPath(), false)
            : ['status' => 'Unscanned', 'clean' => false, 'adapter' => 'clamav'];
        if (($scan['status'] ?? '') === 'Rejected') {
            throw ValidationException::withMessages(['file' => 'This file was rejected by the malware scan and was not saved.']);
        }
        $hash = hash_file('sha256', $upload->getRealPath());
        $storage = app(PrivateStorage::class);
        $key = 'quarantine/tickets/'.Str::uuid();
        $stream = fopen($upload->getRealPath(), 'rb');
        try {
            $storage->put($key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return SupportTicketAttachment::create([
            'support_ticket_message_id' => $message->id,
            'organization_id' => $message->organization_id,
            'uploaded_by' => $request->user()->id,
            'disk' => $storage->disk(),
            'storage_key' => $key,
            'original_name' => Ledger::redact(mb_substr(basename($upload->getClientOriginalName()), 0, 200)),
            'mime' => $mime,
            'bytes' => $upload->getSize(),
            'sha256' => $hash,
            'scan_status' => $scan['status'] ?? 'Unscanned',
        ]);
    }

    public static function readable(SupportTicketAttachment $file): bool
    {
        return in_array($file->scan_status, ['Clean', 'Unscanned', 'Unavailable'], true);
    }

    public static function download(SupportTicketAttachment $file)
    {
        $stream = Storage::disk($file->disk)->readStream($file->storage_key);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $file->mime,
            'X-Scan-Status' => $file->scan_status,
            'Content-Disposition' => 'inline; filename="support-attachment.'.match ($file->mime) {
                'application/pdf' => 'pdf', 'image/png' => 'png', default => 'jpg'
            }.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }
}
