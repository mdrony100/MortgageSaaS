<?php

namespace App\Jobs;

use App\Bank\BankStatus;
use App\Bank\Fraud\FraudCheckException;
use App\Bank\Fraud\SignalPack;
use App\Bank\Providers\OpenAIFraudCheck;
use App\Jobs\Concerns\UsesAnalysisQueue;
use App\Models\Bank\FraudCheck;
use App\Models\Bank\Run;
use App\Models\DocumentVersion;
use App\Models\LoanFile;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Gate;

class RunAiFraudCheck implements ShouldQueue
{
    use Queueable, UsesAnalysisQueue;

    public int $tries = 2;

    public int $timeout = 160;

    public const NOT_CONFIGURED = 'Add an OpenAI API key and bank model in Admin Settings. No document was sent.';

    public function __construct(public string $runId, public int $versionId, public int $checkId)
    {
        $this->assignAnalysisQueue();
    }

    public function backoff(): array
    {
        return [15, 45];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('bank-fraud:'.$this->checkId))->expireAfter(180)->dontRelease()];
    }

    public function handle(): void
    {
        $run = Run::find($this->runId);
        $check = FraudCheck::find($this->checkId);
        if (! $run || ! $check || $run->cancel_requested) {
            return;
        }
        if (in_array($check->status, ['completed', 'failed', 'not_configured'], true)) {
            return;
        }
        $version = DocumentVersion::where('organization_id', $run->organization_id)->find($this->versionId);
        $user = User::find($run->requested_by);
        if (! $version || ! $user || $version->document->loan_file_id !== $run->loan_file_id) {
            $this->failCheck($check, 'The AI fraud check could not read this document version. Run it again.');

            return;
        }
        try {
            Gate::forUser($user)->authorize('review', LoanFile::findOrFail($run->loan_file_id));
            Gate::forUser($user)->authorize('view', $version->document);
        } catch (\Throwable) {
            $this->failCheck($check, 'The AI fraud check could not be authorized for this file.');

            return;
        }
        $model = config('bank.openai.model');
        $key = config('bank.openai.key');
        if (config('bank.fraud.engine') !== 'openai' || ! is_string($key) || $key === '' || ! is_string($model) || $model === '') {
            $check->update([
                'status' => 'not_configured',
                'error_code' => self::NOT_CONFIGURED,
                'model' => null,
                'attempts' => $check->attempts + 1,
            ]);

            return;
        }
        $last = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $check->update(['status' => 'running', 'attempts' => $attempt, 'model' => $model]);
            try {
                $signals = app(SignalPack::class)->forVersion($run, $this->versionId);
                $result = app(OpenAIFraudCheck::class)->review($run, $model, $signals);
                $check->update([
                    'status' => 'completed',
                    'risk_level' => $result['risk_level'],
                    'score' => $result['score'],
                    'summary' => $result['summary'],
                    'findings' => $result['findings'],
                    'signals' => $signals,
                    'error_code' => null,
                    'model' => $model,
                ]);
                $this->advance($run);

                return;
            } catch (FraudCheckException $e) {
                $last = $e;
                if (! $e->retryable) {
                    break;
                }
            }
        }
        $this->failCheck($check, $last?->getMessage() ?: 'The AI fraud check did not finish. Run it again.', $model);
    }

    public function failed(?\Throwable $exception): void
    {
        $check = FraudCheck::find($this->checkId);
        if ($check && in_array($check->status, ['queued', 'running'], true)) {
            $this->failCheck($check, 'The AI fraud check did not finish. Run it again.');
        }
    }

    private function failCheck(FraudCheck $check, string $message, ?string $model = null): void
    {
        $check->update([
            'status' => 'failed',
            'error_code' => mb_substr($message, 0, 400),
            'model' => $model,
        ]);
    }

    private function advance(Run $run): void
    {
        $fresh = $run->fresh();
        if ($fresh && $fresh->status === 'PartiallyCompleted' && BankStatus::integrity($fresh)['external_complete']) {
            $fresh->update(['status' => 'NeedsReview', 'stage' => 'NeedsReview']);
        }
    }
}
