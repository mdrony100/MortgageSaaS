<?php

namespace App\Jobs;

use App\Appraisal\ContinueReview;
use App\Appraisal\Notifications;
use App\Appraisal\Pipeline;
use App\Bank\ProcessingException;
use App\Billing\Usage;
use App\Jobs\Concerns\UsesAnalysisQueue;
use App\Models\Review\Run;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessAppraisal implements ShouldQueue
{
    use Queueable, UsesAnalysisQueue;

    public int $tries = 3;

    public int $timeout = 1200;

    public bool $failOnTimeout = true;

    public function __construct(public string $runId)
    {
        $this->assignAnalysisQueue();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('review:'.$this->runId))->shared()->expireAfter(1250)->releaseAfter(30)];
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(Pipeline $p): void
    {
        $r = Run::findOrFail($this->runId);
        if (in_array($r->status, ['NeedsReview', 'Completed', 'Cancelled'], true)) {
            return;
        }$r->increment('attempts');
        $started = microtime(true);
        try {
            app(Usage::class)->reserve($r->organization_id, 'appraisal:'.$r->id);
            $p->execute($r);
            app(Usage::class)->finish($r->organization_id, 'appraisal:'.$r->id, 'completed');
            app(Notifications::class)->ready($r);
        } catch (ContinueReview $e) {
            if ($r->fresh()->cancel_requested) {
                $r->update(['status' => 'Cancelled']);
                app(Usage::class)->finish($r->organization_id, 'appraisal:'.$r->id, 'cancelled');

                return;
            }$metrics = $r->fresh()->metrics ?? [];
            $metrics['continuations'] = ($metrics['continuations'] ?? 0) + 1;
            $metrics['checkpoint_wall_seconds'] = ($metrics['checkpoint_wall_seconds'] ?? 0) + microtime(true) - $started;
            $r->update(['status' => 'Queued', 'error' => null, 'metrics' => $metrics]);
            self::dispatch($r->id)->delay(now()->addSecond());
        } catch (\Throwable $e) {
            if ($r->fresh()->status === 'Cancelled') {
                app(Usage::class)->finish($r->organization_id, 'appraisal:'.$r->id, 'cancelled');

                return;
            }$metrics = $r->fresh()->metrics ?? [];
            $metrics['failure_attempts'] = ($metrics['failure_attempts'] ?? 0) + 1;
            $safe = $e instanceof ProcessingException ? $e->getMessage() : 'Review failed; sensitive diagnostics omitted';
            $r->update(['status' => 'Failed', 'error' => $safe, 'metrics' => $metrics]);
            app(Usage::class)->finish($r->organization_id, 'appraisal:'.$r->id, 'failed');
            throw new ProcessingException($safe);
        }
    }

    public function failed(?\Throwable $e): void
    {
        $r = Run::find($this->runId);
        if (! $r || in_array($r->status, ['NeedsReview', 'Completed'], true)) {
            return;
        }
        if ($r->status === 'Cancelled') {
            app(Usage::class)->finish($r->organization_id, 'appraisal:'.$r->id, 'cancelled');

            return;
        }
        $r->update(['status' => 'Failed', 'error' => 'Bounded attempts exhausted; check source/provider status']);
        app(Usage::class)->finish($r->organization_id, 'appraisal:'.$r->id, 'failed');
    }
}
