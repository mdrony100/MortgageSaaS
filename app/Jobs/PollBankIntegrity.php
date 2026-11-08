<?php

namespace App\Jobs;

use App\Bank\BankStatus;
use App\Bank\Ledger;
use App\Bank\Pipeline;
use App\Bank\ProcessingException;
use App\Bank\Providers\IntegrityProvider;
use App\Models\Bank\Run;
use App\Models\DocumentVersion;
use App\Models\LoanFile;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PollBankIntegrity implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    public function __construct(public string $runId, public int $versionId, public int $poll = 0)
    {
        $this->onQueue('bank');
    }

    public function backoff(): array
    {
        return [60, 180, 300];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('bank:'.$this->runId))->shared()->expireAfter(180)->releaseAfter(30)];
    }

    public function handle(): void
    {
        $r = Run::findOrFail($this->runId);
        if ($r->cancel_requested) {
            return;
        }
        $file = LoanFile::findOrFail($r->loan_file_id);
        $user = User::findOrFail($r->requested_by);
        Gate::forUser($user)->authorize('review', $file);
        if ($this->poll >= 12) {
            app(Pipeline::class)->finding($r, 'external-poll-exhausted:'.$this->versionId, ['category' => 'provider_status', 'code' => 'external_integrity', 'observation' => 'Specialist polling limit reached; operator review required, not a clean result', 'external' => ['status' => 'Failed', 'reason' => 'Bounded polling exhausted'], 'document_version_id' => $this->versionId]);

            return;
        }
        $input = Ledger::query('bank_inputs', $r)->where('document_version_id', $this->versionId)->first();
        if (! $input) {
            return;
        }
        $v = DocumentVersion::where('organization_id', $r->organization_id)->findOrFail($this->versionId);
        Gate::forUser($user)->authorize('view', $v->document);
        if ($v->document->loan_file_id !== $r->loan_file_id || DB::table('bank_scans')->where('organization_id', $r->organization_id)->where('document_version_id', $v->id)->latest('id')->value('status') !== 'Clean') {
            return;
        }
        $path = tempnam(storage_path('app'), 'bank-original-');
        chmod($path, 0600);
        try {
            $in = Storage::disk($v->disk)->readStream($v->storage_key);
            $out = fopen($path, 'wb');
            stream_copy_to_stream($in, $out, config('bank.max_bytes') + 1);
            fclose($in);
            fclose($out);
            if (! hash_equals($input->sha256, hash_file('sha256', $path))) {
                return;
            }
            $external = app(IntegrityProvider::class)->analyze($r, $v->id, $path, $input->sha256);
            app(Pipeline::class)->finding($r, 'external-status:'.$v->id.':'.Ledger::hash($external), ['category' => 'provider_status', 'code' => 'external_integrity', 'observation' => $external['reason'] ?? ('Specialist integrity '.$external['status']), 'external' => $external, 'document_version_id' => $v->id]);
            foreach ($external['signals'] ?? [] as $i => $s) {
                app(Pipeline::class)->finding($r, 'external:'.$v->id.':'.$i, ['category' => 'integrity_indicator', 'code' => $s['identifier'], 'observation' => $s['observation'], 'document_version_id' => $v->id, 'provider' => $s]);
            }
            if ($external['status'] === 'Pending') {
                self::dispatch($r->id, $v->id, $this->poll + 1)->delay(now()->addSeconds(min(900, 30 * (2 ** min(5, $this->poll)))));
            } elseif (BankStatus::integrity($r)['external_complete'] && $r->status === 'PartiallyCompleted') {
                $r->update(['status' => 'NeedsReview', 'stage' => 'NeedsReview']);
            }
        } catch (\Throwable) {
            throw new ProcessingException('Specialist polling failed; provider status remains incomplete and details are redacted');
        } finally {
            unlink($path);
        }
    }
}
