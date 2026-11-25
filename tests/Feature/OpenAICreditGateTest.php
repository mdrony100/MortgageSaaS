<?php

namespace Tests\Feature;

use App\Appraisal\VisionExtractor;
use App\Bank\ProcessingException;
use App\Bank\Providers\HybridExtractor;
use App\Bank\Providers\OpenAIExtractor;
use App\Billing\PageCredits;
use App\Models\Bank\Run as BankRun;
use App\Models\Borrower;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PageCreditAccount;
use App\Models\Plan;
use App\Models\Review\Run as ReviewRun;
use App\Models\User;
use App\Services\SampleData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\BankFixtures as F;
use Tests\TestCase;

class OpenAICreditGateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $org;

    private LoanFile $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('private');
        $this->owner = User::factory()->create();
        $plan = Plan::query()->where('name', 'Starter')->first() ?? Plan::query()->first();
        $this->org = Organization::create(['name' => 'FICTIONAL credit gate', 'plan_id' => $plan->id]);
        Membership::create(['user_id' => $this->owner->id, 'organization_id' => $this->org->id, 'role' => 'owner', 'org_wide_access' => true]);
        $borrower = Borrower::create(['organization_id' => $this->org->id, 'name' => 'Fictional Borrower']);
        $this->file = LoanFile::create(['organization_id' => $this->org->id, 'borrower_id' => $borrower->id, 'created_by' => $this->owner->id, 'name' => 'Synthetic OpenAI review', 'is_demo' => true, 'module' => 'bank']);
        $this->actingAs($this->owner);
    }

    public function test_org_with_credits_and_no_bank_limits_can_process_openai_bank_and_vision(): void
    {
        $this->assertSame(0, DB::table('bank_limits')->where('organization_id', $this->org->id)->count());
        $balance = (int) PageCreditAccount::where('organization_id', $this->org->id)->value('balance');
        $this->assertGreaterThan(0, $balance);
        $this->configureOpenAi(0, 0);
        app(PageCredits::class)->consume($this->org->id, 1, 'bank:credit-gate:1', [
            'reason' => 'Bank statement pages processed',
            'actor_id' => $this->owner->id,
            'loan_file_id' => $this->file->id,
            'pipeline' => 'bank',
        ], true);
        $this->assertSame($balance - 1, (int) PageCreditAccount::where('organization_id', $this->org->id)->value('balance'));

        $dir = $this->pageDir();
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => fn ($request) => Http::response($this->providerBody($request))]);
        try {
            $bank = (new OpenAIExtractor)->extract($this->manifest(), $this->bankRun(), 1, $dir);
            $this->assertSame('bank_statement', $bank['document_type']);
            $this->assertSame(0, DB::table('bank_limits')->count());
            $call = DB::table('bank_provider_calls')->first();
            $this->assertSame(0, (int) $call->reserved_micro_usd);
            $this->assertSame(200, json_decode($call->data, true)['usage']['total_tokens']);

            $vision = app(VisionExtractor::class)->extract($this->manifest(), $this->reviewRun(), 1, $dir);
            $this->assertSame('appraisal', $vision['classification']);
            $this->assertSame('1004', $vision['form']);
            $recorded = json_decode(DB::table('review_calls')->first()->data, true);
            $this->assertSame('completed', $recorded['status']);
            $this->assertSame(15, $recorded['usage']['total_tokens']);
            $this->assertSame(0, $recorded['reserved_micro_usd']);
            $this->assertSame(0, DB::table('bank_limits')->count());

            DB::table('bank_limits')->insert(['organization_id' => $this->org->id, 'limit_micro_usd' => 0, 'per_run_limit_micro_usd' => 0, 'reserved_micro_usd' => 0, 'created_at' => now(), 'updated_at' => now()]);
            config(['bank.openai.reservation_micro_usd_per_group' => 7]);
            $again = (new OpenAIExtractor)->extract($this->manifest(), $this->bankRun(), 2, $dir);
            $this->assertSame('bank_statement', $again['document_type']);
            $this->assertSame(7, (int) DB::table('bank_limits')->value('reserved_micro_usd'));
            $this->assertSame(2, DB::table('bank_provider_calls')->count());
            Http::assertSentCount(3);
        } finally {
            $this->removePageDir($dir);
        }
    }

    public function test_incomplete_native_statement_uses_openai_without_a_dollar_budget(): void
    {
        $this->assertGreaterThan(0, (int) PageCreditAccount::where('organization_id', $this->org->id)->value('balance'));
        $this->assertSame(0, DB::table('bank_limits')->count());
        $this->configureOpenAi(0, 0);
        $dir = $this->pageDir();
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => fn ($request) => Http::response($this->providerBody($request))]);
        try {
            $out = (new HybridExtractor)->extract($this->manifest('scanned statement with no labeled columns'), $this->bankRun(), 3, $dir);
            $this->assertSame('bank_statement', $out['document_type']);
            Http::assertSentCount(1);
            $this->assertSame(0, DB::table('bank_limits')->count());
        } finally {
            $this->removePageDir($dir);
        }
    }

    public function test_appraisal_vision_stays_off_until_approval_model_and_key_are_set(): void
    {
        $dir = $this->pageDir();
        Http::preventStrayRequests();
        Http::fake();
        try {
            config(['review.vision_approved' => false, 'review.model' => 'TEST-MOCK-NOT-LIVE', 'bank.openai.key' => 'TEST-MOCK-NOT-REAL']);
            $off = app(VisionExtractor::class)->extract($this->manifest(), $this->reviewRun(), 4, $dir);
            $this->assertNull($off['classification']);
            config(['review.vision_approved' => true, 'review.model' => null, 'bank.openai.key' => 'TEST-MOCK-NOT-REAL']);
            $noModel = app(VisionExtractor::class)->extract($this->manifest(), $this->reviewRun(), 5, $dir);
            $this->assertNull($noModel['classification']);
            config(['review.vision_approved' => true, 'review.model' => 'TEST-MOCK-NOT-LIVE', 'bank.openai.key' => '']);
            $noKey = app(VisionExtractor::class)->extract($this->manifest(), $this->reviewRun(), 6, $dir);
            $this->assertNull($noKey['classification']);
            Http::assertNothingSent();
        } finally {
            $this->removePageDir($dir);
        }
    }

    public function test_zero_page_credits_block_processing_with_the_page_credit_message(): void
    {
        PageCreditAccount::where('organization_id', $this->org->id)->update(['balance' => 0, 'used_this_period' => 500]);
        $this->assertSame(0, DB::table('bank_limits')->count());
        $this->configureOpenAi(0, 0);
        Http::fake(['https://api.openai.com/*' => Http::response($this->bankBody(), 200)]);

        try {
            app(PageCredits::class)->consume($this->org->id, 1, 'bank:credit-gate:empty', [
                'reason' => 'Bank statement pages processed',
                'actor_id' => $this->owner->id,
                'loan_file_id' => $this->file->id,
                'pipeline' => 'bank',
            ], true);
            $this->fail('Zero page credits must stop processing');
        } catch (ProcessingException $e) {
            $this->assertSame(PageCredits::INSUFFICIENT, $e->getMessage());
            $this->assertStringNotContainsString('budget', strtolower($e->getMessage()));
        }
        Http::assertNothingSent();
        $this->assertSame(0, DB::table('bank_provider_calls')->count());

        $uploaded = $this->post('/api/files/'.$this->file->id.'/documents', [
            'file' => UploadedFile::fake()->createWithContent('FICTIONAL.pdf', SampleData::pdf()),
            'fictional_sample' => true,
        ], ['Accept' => 'application/json'])->assertCreated();
        $versionId = $uploaded->json('version.id');
        $this->postJson('/api/files/'.$this->file->id.'/bank/runs', [
            'idempotency_key' => 'no-pages-bank',
            'document_version_ids' => [$versionId],
            'workflow' => 'personal',
            'months' => 12,
            'end_month' => '2025-12',
        ])->assertUnprocessable()->assertJsonPath('errors.pages.0', PageCredits::INSUFFICIENT);
        $this->postJson('/api/files/'.$this->file->id.'/appraisal/runs', [
            'idempotency_key' => 'no-pages-appraisal',
            'document_version_ids' => [$versionId],
            'context' => ['loan_occupancy' => 'investment', 'transaction' => 'purchase', 'units' => 1],
        ])->assertUnprocessable()->assertJsonPath('errors.pages.0', PageCredits::INSUFFICIENT);
        $this->assertSame(0, BankRun::count());
        $this->assertSame(0, ReviewRun::count());
        $this->assertSame(0, (int) PageCreditAccount::where('organization_id', $this->org->id)->value('balance'));
    }

    private function configureOpenAi(int $bankReservation, int $visionReservation): void
    {
        config([
            'bank.openai.approved' => true,
            'bank.openai.key' => 'TEST-MOCK-NOT-REAL',
            'bank.openai.model' => 'TEST-MOCK-NOT-LIVE',
            'bank.openai.secondary_model' => null,
            'bank.openai.reservation_micro_usd_per_group' => $bankReservation,
            'review.vision_approved' => true,
            'review.model' => 'TEST-MOCK-NOT-LIVE',
            'review.reservation_micro_usd' => $visionReservation,
        ]);
    }

    private function bankRun(): BankRun
    {
        return BankRun::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'loan_file_id' => $this->file->id,
            'requested_by' => $this->owner->id,
            'idempotency_key' => (string) Str::uuid(),
            'status' => 'Queued',
            'stage' => 'Queued',
            'options' => ['workflow' => 'business'],
            'input_hash' => str_repeat('a', 64),
        ]);
    }

    private function reviewRun(): ReviewRun
    {
        return ReviewRun::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $this->org->id,
            'loan_file_id' => $this->file->id,
            'requested_by' => $this->owner->id,
            'idempotency_key' => (string) Str::uuid(),
            'inputs' => [],
            'context' => ['loan_occupancy' => 'investment', 'transaction' => 'purchase', 'units' => 1],
            'checklist' => [],
        ]);
    }

    private function manifest(string $text = 'UNTRUSTED scanned page'): array
    {
        return ['pages' => [['number' => 1, 'text' => $text, 'render' => 'page.png']]];
    }

    private function pageDir(): string
    {
        $dir = sys_get_temp_dir().'/openai-credits-'.Str::uuid();
        mkdir($dir, 0700);
        file_put_contents($dir.'/page.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII='));

        return $dir;
    }

    private function removePageDir(string $dir): void
    {
        @unlink($dir.'/page.png');
        @rmdir($dir);
    }

    private function providerBody($request): array
    {
        $name = $request['text']['format']['name'] ?? '';
        if ($name === 'appraisal_evidence') {
            return ['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($this->visionBody())]]]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15]];
        }

        return $this->bankBody();
    }

    private function bankBody(): array
    {
        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(F::statement())]]]], 'usage' => ['input_tokens' => 100, 'output_tokens' => 100, 'total_tokens' => 200]];
    }

    private function visionBody(): array
    {
        return ['classification' => 'appraisal', 'form' => '1004', 'form_version' => '2005', 'complete' => true, 'pages' => [1], 'fields' => [], 'photos' => [], 'issues' => []];
    }
}
