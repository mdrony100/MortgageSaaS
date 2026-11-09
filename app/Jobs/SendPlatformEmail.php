<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Plain-text owner-console notifications (approvals, invitations). Failures never undo the action. */
class SendPlatformEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public string $to, public string $subject, public string $body) {}

    public function handle(): void
    {
        Mail::raw($this->body, fn ($m) => $m->to($this->to)->subject($this->subject));
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Owner console email failed', ['exception_type' => get_class($e)]);
    }

    /** Queue the email; any dispatch or synchronous delivery failure is logged, never thrown. */
    public static function safely(string $to, string $subject, string $body): bool
    {
        try {
            app(Dispatcher::class)->dispatch((new self($to, $subject, $body))->afterCommit());

            return true;
        } catch (\Throwable $e) {
            Log::warning('Owner console email could not be queued', ['exception_type' => get_class($e)]);

            return false;
        }
    }
}
