<?php

namespace App\Jobs;

use App\Models\AnalysisRun;
use App\Models\AnalysisSnapshot;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LoanFile;
use App\Models\Ruleset;
use App\Services\Audit;
use App\Services\PrivateStorage;
use App\Services\SampleData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoadSampleAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [5, 30, 60];

    public function __construct(public string $runId, public int $actorId) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $run = AnalysisRun::lockForUpdate()->findOrFail($this->runId);
            if ($run->status === 'completed') {
                return;
            } $file = LoanFile::findOrFail($run->loan_file_id);
            if (! $file->is_demo) {
                throw new \LogicException('Sample analysis only applies to explicitly fictional files.');
            } $run->update(['status' => 'running', 'attempts' => $run->attempts + 1]);
            $doc = Document::firstOrCreate(['organization_id' => $file->organization_id, 'loan_file_id' => $file->id, 'category' => 'fixture'], ['title' => 'Fictional source evidence']);
            $version = $doc->versions()->first();
            if (! $version) {
                $pdf = SampleData::pdf();
                $storage = app(PrivateStorage::class);
                $key = 'fixtures/'.Str::uuid();
                $storage->put($key, $pdf);
                $version = DocumentVersion::create(['organization_id' => $file->organization_id, 'document_id' => $doc->id, 'uploaded_by' => $this->actorId, 'version' => 1, 'disk' => $storage->disk(), 'storage_key' => $key, 'original_name' => 'fictional-evidence.pdf', 'mime' => 'application/pdf', 'bytes' => strlen($pdf), 'sha256' => hash('sha256', $pdf), 'scan_status' => 'not_configured', 'validation_status' => 'generated_fictional_fixture', 'status' => 'Fictional sample source — not extracted']);
            }
            $rules = Ruleset::firstOrCreate(['organization_id' => $file->organization_id, 'name' => 'Fictional demonstration rules', 'version' => 1], ['status' => 'fictional_draft', 'rules' => ['notice' => 'Not investor-approved; no lender rules configured.']]);
            $data = SampleData::all($version->id);
            AnalysisSnapshot::firstOrCreate(['analysis_run_id' => $run->id], ['organization_id' => $file->organization_id, 'ruleset_id' => $rules->id, 'data' => $data, 'is_demo' => true]);
            foreach (['source_evidence' => ['evidence-1', $data['source']], 'classification_suggestions' => ['tx-1', $data['bank']['transactions'][0]], 'report_snapshots' => ['sample-report', ['title' => 'Fictional report', 'run_id' => $run->id, 'notice' => 'Preview only; no lending decision']]] as $table => [$key,$value]) {
                DB::table($table)->updateOrInsert(['analysis_run_id' => $run->id, 'external_key' => $key], ['organization_id' => $file->organization_id, 'loan_file_id' => $file->id, 'data' => json_encode($value), 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
            $run->update(['status' => 'completed', 'stage' => 'sample_ready', 'completed_at' => now(), 'error' => null]);
            Audit::record('sample_analysis.loaded', $file->organization_id, $file->id, [], 'shared');
        });
    }

    public function failed(?\Throwable $error): void
    {
        AnalysisRun::whereKey($this->runId)->update(['status' => 'failed', 'error' => 'Sample fixture job failed. Retry with the same idempotency key.']);
    }
}
