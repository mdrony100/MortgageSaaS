<?php

namespace App\Jobs;

use App\Bank\Pipeline;
use App\Bank\ProcessingException;
use App\Billing\Usage;
use App\Models\Bank\Run;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessBankRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1200;

    public bool $failOnTimeout = true;

    public function __construct(public string $runId)
    {
        $this->onQueue('bank');
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('bank:'.$this->runId))->shared()->expireAfter(1250)->releaseAfter(30)];
    }

    public function handle(Pipeline $pipeline): void
    {
        $r = Run::findOrFail($this->runId);
        if (in_array($r->status, ['Completed', 'PartiallyCompleted', 'NeedsReview', 'Cancelled'], true)) {
            return;
        }
        $r->increment('attempts');
        try {
            app(Usage::class)->reserve($r->organization_id, 'bank:'.$r->idempotency_key);
            $pipeline->execute($r);
            app(Usage::class)->finish($r->organization_id, 'bank:'.$r->idempotency_key, 'completed');
        } catch (\Throwable $e) {
            if ($r->fresh()->status === 'Cancelled') {
                app(Usage::class)->finish($r->organization_id, 'bank:'.$r->idempotency_key, 'cancelled');

                return;
            }
            app(Usage::class)->finish($r->organization_id, 'bank:'.$r->idempotency_key, 'failed');
            $message = $e instanceof ProcessingException ? $e->getMessage() : 'Processing error; internal details redacted';
            $r->update(['status' => 'Failed', 'stage' => 'Failed', 'error_code' => mb_substr($message, 0, 240)]);
            // A sanitized exception avoids raw provider/document data in failed_jobs logs.
            throw new \RuntimeException($message);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $r = Run::find($this->runId);
        if (! $r || in_array($r->status, ['Completed', 'PartiallyCompleted', 'NeedsReview'], true)) {
            return;
        }
        if ($r->status !== 'Cancelled') {
            $r->update(['status' => 'Failed', 'stage' => 'Failed', 'error_code' => 'Bounded attempts exhausted; review source and provider status']);
        }
        app(Usage::class)->finish($r->organization_id, 'bank:'.$r->idempotency_key, $r->status === 'Cancelled' ? 'cancelled' : 'failed');
    }
}
