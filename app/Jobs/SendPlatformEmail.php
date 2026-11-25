<?php

namespace App\Jobs;

use App\Mail\BrandedMessage;
use App\Services\NotificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Queued workspace email on the shared branded layout. Failures never undo the action. */
class SendPlatformEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
        public ?string $group = null,
    ) {}

    public function handle(): void
    {
        if ($this->group && ! NotificationMail::enabled($this->group)) {
            return;
        }
        Mail::to($this->to)->send(BrandedMessage::fromPlain($this->subject, $this->body));
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Owner console email failed', ['exception_type' => get_class($e)]);
    }

    /**
     * Queue the email. A dispatch or synchronous delivery failure is logged, never thrown.
     * Invitations pass $afterCommit false so a rejected message can be reported on the response.
     */
    public static function safely(string $to, string $subject, string $body, ?string $group = null, bool $afterCommit = true): bool
    {
        if ($group && ! NotificationMail::enabled($group)) {
            return false;
        }
        try {
            $job = new self($to, $subject, $body, $group);
            if ($afterCommit) {
                $job->afterCommit();
            }
            app(Dispatcher::class)->dispatch($job);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Owner console email could not be queued', ['exception_type' => get_class($e)]);

            return false;
        }
    }
}
