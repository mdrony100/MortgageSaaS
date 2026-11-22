<?php

namespace Tests\Feature;

use App\Bank\Ledger;
use App\Bank\Pipeline;
use App\Bank\StatementExtractor;
use App\Jobs\ProcessBankRun;
use App\Jobs\RunAiFraudCheck;
use App\Models\Bank\FraudCheck;
use App\Models\Bank\Run;
use App\Models\Borrower;
use App\Models\DocumentVersion;
use App\Models\FileShare;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\BankFixtures as F;
use Tests\Support\FixtureExtractor;
use Tests\TestCase;

require_once __DIR__.'/../Support/BankFixtures.php';

class AiFraudCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $org;

    private LoanFile $file;

    private FixtureExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed();
        $this->owner = User::factory()->create();
        $this->org = Organization::create(['name' => 'FICTIONAL fraud-check tests', 'plan_id' => Plan::first()->id]);
        Membership::create(['user_id' => $this->owner->id, 'organization_id' => $this->org->id, 'role' => 'owner', 'org_wide_access' => true]);
        $borrower = Borrower::create(['organization_id' => $this->org->id, 'name' => 'Fictional Fraud Borrower']);
        $this->file = LoanFile::create(['organization_id' => $this->org->id, 'borrower_id' => $borrower->id, 'created_by' => $this->owner->id, 'name' => 'Synthetic fraud review', 'is_demo' => true, 'module' => 'bank']);
        $this->actingAs($this->owner);
        $this->extractor = new FixtureExtractor;
        $this->app->instance(StatementExtractor::class, $this->extractor);
        config([
            'bank.fictional_scan_bypass' => true,
            'bank.integrity.enabled' => false,
            'bank.integrity.provider' => 'disabled',
            'bank.fraud.engine' => 'openai',
            'bank.openai.key' => null,
            'bank.openai.model' => null,
            'bank.openai.approved' => false,
        ]);
        Http::preventStrayRequests();
    }

    public function test_parsed_statement_sends_signals_not_pdf_bytes_and_rerun_replaces_the_result(): void
    {
        $statement = F::statement();
        $bytes = F::pdf($statement);
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($this->openai($this->modelResult()))]);
        config(['bank.openai.key' => 'FICTIONAL-OPENAI-KEY', 'bank.openai.model' => 'TEST-FRAUD-NOT-LIVE']);
        $versionId = $this->upload($statement, $bytes);
        $run = $this->start($versionId);
        $this->process($run);

        Http::assertSent(function ($request) use ($bytes) {
            $body = $request->body();
            $this->assertStringNotContainsString('%PDF-', $body);
            $this->assertStringNotContainsString($bytes, $body);
            $this->assertStringContainsString('Merchant sales', $body);
            $this->assertStringContainsString('running_balance_breaks', $body);
            $this->assertStringContainsString('holder_or_account_mismatches', $body);
            $this->assertStringContainsString('duplicate_deposit_amounts', $body);
            $this->assertStringContainsString('nsf_count', $body);
            $this->assertStringContainsString('"store":false', $body);
            $this->assertStringContainsString('fraud_check', $body);
            $this->assertSame('https://api.openai.com/v1/responses', $request->url());

            return $request->hasHeader('Authorization', 'Bearer FICTIONAL-OPENAI-KEY');
        });
        $saved = FraudCheck::where('run_id', $run->id)->where('status', 'completed')->first();
        $this->assertNotNull($saved);
        $this->assertSame('medium', $saved->risk_level);
        $this->assertSame(46, $saved->score);
        $this->assertSame(1, $saved->findings[0]['page']);
        $this->assertStringNotContainsString('%PDF-', json_encode($saved->signals));
        $this->assertSame('TEST-FRAUD-NOT-LIVE', $saved->model);

        $page = $this->getJson('/api/files/'.$this->file->id.'/bank?run_id='.$run->id)->assertOk();
        $page->assertJsonPath('fraud_checks.0.risk_level', 'medium')
            ->assertJsonPath('fraud_checks.0.score', 46)
            ->assertJsonPath('fraud_checks.0.findings.0.title', 'PDF dates differ')
            ->assertJsonPath('fraud_checks.0.findings.0.page', 1);
        $this->assertStringNotContainsString('Ocrolus', $page->getContent());
        $this->assertStringNotContainsString('FICTIONAL-OPENAI-KEY', $page->getContent());
        $this->assertStringNotContainsString('%PDF-', $page->getContent());

        Ledger::append('bank_statements', $run->fresh(), 'gap-statement', [
            'institution' => 'FICTIONAL Test Bank', 'holder' => 'Other Fictional LLC', 'masked_account' => '****1111',
            'period_start' => '2025-03-01', 'period_end' => '2025-03-31', 'opening_balance' => '100000.00', 'closing_balance' => '100000.00',
            'document_version_id' => $versionId, 'account_key' => 'other-account', 'currency' => 'USD', 'complete' => true,
        ]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($this->openai($this->modelResult('low', 12, 'Second fictional pass.')))]);
        $this->postJson('/api/files/'.$this->file->id.'/bank/runs/'.$run->id.'/fraud/'.$versionId)->assertStatus(202)
            ->assertJsonPath('fraud_check.risk_level', 'low')
            ->assertJsonPath('fraud_check.score', 12);
        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, 'Other Fictional LLC') && str_contains($body, 'date_gaps') && ! str_contains($body, '%PDF-');
        });
        $this->assertSame('low', FraudCheck::where('run_id', $run->id)->orderByDesc('id')->value('risk_level'));
    }

    public function test_missing_openai_key_is_explicit_and_sends_nothing(): void
    {
        $versionId = $this->upload(F::statement(), F::pdf(F::statement()));
        $run = $this->start($versionId);
        $this->process($run);
        $check = FraudCheck::where('run_id', $run->id)->first();
        $this->assertNotNull($check);
        $this->assertSame('not_configured', $check->status);
        $this->assertSame(RunAiFraudCheck::NOT_CONFIGURED, $check->error_code);
        Http::assertNothingSent();
        $this->getJson('/api/files/'.$this->file->id.'/bank?run_id='.$run->id)->assertOk()
            ->assertJsonPath('fraud_checks.0.status', 'not_configured')
            ->assertJsonPath('fraud_checks.0.message', RunAiFraudCheck::NOT_CONFIGURED);
        $this->postJson('/api/files/'.$this->file->id.'/bank/runs/'.$run->id.'/fraud/'.$versionId)->assertStatus(202)
            ->assertJsonPath('fraud_check.status', 'not_configured');
        Http::assertNothingSent();
        $this->assertNotSame('Failed', $run->fresh()->status);
    }

    public function test_provider_timeout_retries_marks_failed_and_rerun_can_succeed(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('FICTIONAL timeout');
        });
        config(['bank.openai.key' => 'FICTIONAL-OPENAI-KEY', 'bank.openai.model' => 'TEST-FRAUD-NOT-LIVE']);
        $versionId = $this->upload(F::statement(), F::pdf(F::statement()));
        $run = $this->start($versionId);
        $this->process($run);
        $check = FraudCheck::where('run_id', $run->id)->first();
        $this->assertSame('failed', $check->status);
        $this->assertSame(3, $check->attempts);
        $this->assertStringContainsString('timed out', $check->error_code);
        $this->assertSame(3, $attempts);
        $this->assertNotSame('Failed', $run->fresh()->status);
        $this->assertStringNotContainsString('FICTIONAL timeout', $check->error_code);

        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response($this->openai($this->modelResult('high', 80, 'Fictional rerun after a timeout.')))]);
        $this->postJson('/api/files/'.$this->file->id.'/bank/runs/'.$run->id.'/fraud/'.$versionId)->assertStatus(202)
            ->assertJsonPath('fraud_check.status', 'completed')
            ->assertJsonPath('fraud_check.risk_level', 'high')
            ->assertJsonPath('fraud_check.score', 80);
        $this->assertStringNotContainsString('%PDF-', Http::recorded()[0][0]->body());
    }

    public function test_invalid_model_output_is_rejected_and_a_running_check_cannot_be_duplicated(): void
    {
        Http::fake(['https://api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => '{"risk_level":"severe"}']]]]])]);
        config(['bank.openai.key' => 'FICTIONAL-OPENAI-KEY', 'bank.openai.model' => 'TEST-FRAUD-NOT-LIVE']);
        $versionId = $this->upload(F::statement(), F::pdf(F::statement()));
        $run = $this->start($versionId);
        $this->process($run);
        $check = FraudCheck::where('run_id', $run->id)->first();
        $this->assertSame('failed', $check->status);
        $this->assertStringContainsString('could not be read', $check->error_code);
        $check->update(['status' => 'running', 'updated_at' => now()]);
        $this->postJson('/api/files/'.$this->file->id.'/bank/runs/'.$run->id.'/fraud/'.$versionId)->assertStatus(422);
    }

    public function test_processor_cannot_read_or_rerun_the_fraud_check(): void
    {
        $versionId = $this->upload(F::statement(), F::pdf(F::statement()));
        $run = $this->start($versionId);
        FraudCheck::create([
            'organization_id' => $this->org->id, 'loan_file_id' => $this->file->id, 'run_id' => $run->id,
            'document_version_id' => $versionId, 'status' => 'completed', 'risk_level' => 'high', 'score' => 91,
            'summary' => 'Restricted fictional signal.', 'findings' => [], 'attempts' => 1,
        ]);
        $processor = User::factory()->create();
        Membership::create(['user_id' => $processor->id, 'organization_id' => $this->org->id, 'role' => 'processor', 'org_wide_access' => false]);
        FileShare::create(['organization_id' => $this->org->id, 'loan_file_id' => $this->file->id, 'user_id' => $processor->id, 'shared_by' => $this->owner->id]);
        $this->actingAs($processor)->getJson('/api/files/'.$this->file->id.'/bank')->assertOk()
            ->assertJsonMissingPath('fraud_checks');
        $this->assertStringNotContainsString('Restricted fictional signal.', $this->getJson('/api/files/'.$this->file->id.'/bank')->getContent());
        $this->postJson('/api/files/'.$this->file->id.'/bank/runs/'.$run->id.'/fraud/'.$versionId)->assertForbidden();
    }

    public function test_customer_and_admin_screens_do_not_name_the_retired_provider(): void
    {
        foreach ([resource_path('js'), resource_path('views')] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($files as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $text = (string) file_get_contents($file->getPathname());
                $this->assertStringNotContainsStringIgnoringCase('ocrolus', $text, $file->getPathname());
            }
        }
    }

    private function upload(array $statement, string $bytes): int
    {
        $response = $this->post('/api/files/'.$this->file->id.'/documents', [
            'file' => UploadedFile::fake()->createWithContent('FICTIONAL-'.$statement['period_start'].'.pdf', $bytes),
            'fictional_sample' => true,
        ], ['Accept' => 'application/json'])->assertCreated();
        $id = $response->json('version.id');
        $this->extractor->byVersion[$id] = $statement;

        return $id;
    }

    private function start(int $versionId): Run
    {
        $response = $this->postJson('/api/files/'.$this->file->id.'/bank/runs', [
            'idempotency_key' => 'fraud-'.DocumentVersion::findOrFail($versionId)->sha256,
            'document_version_ids' => [$versionId],
            'workflow' => 'business',
            'months' => 12,
            'end_month' => '2025-12',
        ])->assertAccepted();

        return Run::findOrFail($response->json('run_id'));
    }

    private function process(Run $run): void
    {
        if (in_array($run->fresh()->status, ['Completed', 'PartiallyCompleted', 'NeedsReview', 'Cancelled'], true)) {
            return;
        }
        (new ProcessBankRun($run->id))->handle(app(Pipeline::class));
    }

    private function modelResult(string $level = 'medium', int $score = 46, string $summary = 'Fictional dates differ. This is a risk signal only.'): array
    {
        return [
            'risk_level' => $level,
            'score' => $score,
            'summary' => $summary,
            'findings' => [[
                'title' => 'PDF dates differ',
                'evidence' => 'Creation and modification dates are not the same.',
                'page' => 1,
            ]],
        ];
    }

    private function openai(array $result): array
    {
        return [
            'status' => 'completed',
            'usage' => ['input_tokens' => 12, 'output_tokens' => 20, 'total_tokens' => 32],
            'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($result)]]]],
        ];
    }
}
