<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Queues email verification and password reset. The notification still uses Laravel's mail channel. */
class SendAuthNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public string $kind, public int $userId, public ?string $token = null) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user || ! $user->email) {
            return;
        }
        if ($this->kind === 'reset') {
            $user->notify(new ResetPassword((string) $this->token));

            return;
        }
        $user->notify(new VerifyEmail);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Auth email failed', ['kind' => $this->kind, 'user_id' => $this->userId, 'exception_type' => $e::class]);
    }
}
