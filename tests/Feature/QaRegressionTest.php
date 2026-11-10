<?php

namespace Tests\Feature;

use App\Appraisal\Store;
use App\Bank\Pipeline;
use App\Bank\Providers\NativeExtractor;
use App\Bank\ReviewState;
use App\Bank\RuleProfile;
use App\Bank\StatementExtractor;
use App\Exceptions\MailDeliveryException;
use App\Jobs\ProcessAppraisal;
use App\Jobs\ProcessBankRun;
use App\Models\Bank\Run;
use App\Models\Borrower;
use App\Models\DocumentVersion;
use App\Models\Invitation;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Regression coverage for QA fixes and for the fictional files in qa/fixtures.
 * Bank and appraisal cases call the real Python parser and NativeExtractor.
 * They are fixture integration tests, not a substitute for the live HTTP walkthrough.
 */
class QaRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('private');
    }

    public function test_smtp_rejection_keeps_the_account_and_resend_reports_the_failure(): void
    {
        Event::listen(MessageSending::class, function () {
            throw new TransportException('550 5.7.1 Message classified as SPAM');
        });
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Fictional Avery Sample',
            'organization_name' => 'Fictional QA Workspace',
            'email' => 'qa-verify@example.test',
            'password' => 'Fictional-pass-1',
            'password_confirmation' => 'Fictional-pass-1',
        ])->assertCreated();
        $response->assertJsonPath('verification_email_sent', false);
        $this->assertStringContainsString('could not be delivered', $response->json('message'));
        $this->assertAuthenticated();
        $user = User::where('email', 'qa-verify@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        $this->postJson('/api/auth/email/verification-notification')
            ->assertStatus(503)
            ->assertJsonPath('verification_email_sent', false);
        $this->assertStringNotContainsString('550', $this->postJson('/api/auth/email/verification-notification')->json('message'));

        $this->postJson('/api/auth/logout');
        $this->postJson('/api/auth/forgot-password', ['email' => 'qa-verify@example.test'])
            ->assertStatus(503)
            ->assertJsonFragment(['message' => 'The password reset email could not be delivered. Try again later, or ask an administrator to send a reset link. MortgageLens never sets a password for you.']);
    }

    public function test_invitation_is_kept_when_smtp_rejects_the_message(): void
    {
        Event::listen(MessageSending::class, function () {
            throw new TransportException('550 classified as SPAM');
        });
        [$owner, $org] = $this->workspace();
        $response = $this->actingAs($owner)->postJson('/api/organizations/'.$org->id.'/invitations', [
            'email' => 'new-member@example.test',
            'role' => 'broker',
        ])->assertCreated();
        $response->assertJsonPath('email_delivered', false);
        $this->assertStringContainsString('could not be delivered', $response->json('message'));
        $this->assertNotNull(Invitation::where('email', 'new-member@example.test')->whereNull('revoked_at')->first());
        $this->assertTrue(class_exists(MailDeliveryException::class));
    }

    public function test_database_queue_receives_analysis_jobs_without_redis(): void
    {
        config(['queue.default' => 'database']);
        $this->assertSame('default', (new ProcessBankRun('x'))->queue);
        $this->assertSame('default', (new ProcessAppraisal('x'))->queue);
        config(['queue.default' => 'redis']);
        $this->assertSame('bank', (new ProcessBankRun('x'))->queue);
        $this->assertSame('bank', (new ProcessAppraisal('x'))->queue);
    }

    public function test_trigger_migrations_use_postgresql_10_procedure_syntax(): void
    {
        $sql = '';
        foreach (glob(base_path('database/migrations/*.php')) as $file) {
            $sql .= file_get_contents($file);
        }
        $this->assertStringNotContainsString('EXECUTE FUNCTION', $sql);
        $this->assertStringContainsString('EXECUTE PROCEDURE mortgage_immutable()', $sql);
        $this->assertTrue(Event::hasListeners(Registered::class));
    }

    public function test_personal_twelve_month_fixture_matches_hand_calculation_and_exports(): void
    {
        $this->requireParser();
        [$owner, $org] = $this->workspace();
        $file = $this->loanFile($org, $owner, 'bank');
        $this->actingAs($owner);
        $versions = $this->uploadDir($file, 'qa/fixtures/bank/personal-12');
        $run = $this->processBank($file, $versions, 'personal', 12, '2025-12');
        $this->assertContains($run->fresh()->status, ['NeedsReview', 'PartiallyCompleted', 'Completed']);
        $calc = $this->calculate($file, $run, 'personal');
        $this->assertSame(5330000, $calc['data']['observed_deposits_minor']);
        $this->assertSame(4800000, $calc['data']['eligible_minor']);
        $this->assertSame(530000, $calc['data']['excluded_minor']);
        $this->assertSame('4000.00', $calc['data']['monthly_display']);
        $this->assertSame(12, $calc['data']['months']);
        $this->assertSame('provisional', $calc['data']['status']);
        $this->assertSame(1, $calc['data']['nsf']['cumulative']['nsf_events']);
        $this->assertSame(1, $calc['data']['nsf']['cumulative']['overdraft_events']);
        $this->assertSame(3500, $calc['data']['nsf']['cumulative']['nsf_fee_minor']);
        $this->assertSame(3500, $calc['data']['nsf']['cumulative']['overdraft_fee_minor']);
        $this->assertExport($file, $run, $calc, '4000.00', 12);
    }

    public function test_missing_february_keeps_the_twelve_month_denominator(): void
    {
        $this->requireParser();
        [$owner, $org] = $this->workspace();
        $file = $this->loanFile($org, $owner, 'bank');
        $this->actingAs($owner);
        $versions = $this->uploadDir($file, 'qa/fixtures/bank/personal-missing-february');
        $run = $this->processBank($file, $versions, 'personal', 12, '2025-12');
        $calc = $this->calculate($file, $run, 'personal');
        $this->assertSame(4930000, $calc['data']['observed_deposits_minor']);
        $this->assertSame(4400000, $calc['data']['eligible_minor']);
        $this->assertSame('3666.67', $calc['data']['monthly_display']);
        $this->assertSame(12, $calc['data']['months']);
        $codes = array_map(fn ($f) => $f['data']['code'] ?? null, ReviewState::findings($run));
        $this->assertContains('missing_statement_period', $codes);
        $this->assertContains('balance_continuity', $codes);
    }

    public function test_business_twenty_four_month_fixture_uses_half_remaining_income(): void
    {
        $this->requireParser();
        [$owner, $org] = $this->workspace();
        $file = $this->loanFile($org, $owner, 'bank');
        $this->actingAs($owner);
        $versions = $this->uploadDir($file, 'qa/fixtures/bank/business-24');
        $run = $this->processBank($file, $versions, 'business', 24, '2025-12');
        $calc = $this->calculate($file, $run, 'business');
        $this->assertSame(24000000, $calc['data']['observed_deposits_minor']);
        $this->assertSame(24000000, $calc['data']['eligible_minor']);
        $this->assertSame('5000.00', $calc['data']['monthly_display']);
        $this->assertSame(24, $calc['data']['months']);
        $this->assertSame(0, $calc['data']['nsf']['cumulative']['nsf_events']);
        $this->assertSame(0, $calc['data']['nsf']['cumulative']['overdraft_events']);
    }

    public function test_transfer_is_excluded_once_and_bad_files_do_not_invent_numbers(): void
    {
        $this->requireParser();
        [$owner, $org] = $this->workspace();
        $file = $this->loanFile($org, $owner, 'bank');
        $this->actingAs($owner);
        $versions = $this->uploadDir($file, 'qa/fixtures/bank/transfers');
        $run = $this->processBank($file, $versions, 'personal', 12, '2025-12');
        $accounts = ReviewState::accounts($run);
        $personal = collect($accounts)->first(fn ($a) => $a['designation'] === 'personal');
        $business = collect($accounts)->first(fn ($a) => $a['designation'] === 'business');
        foreach ([$personal, $business] as $account) {
            $this->postJson($this->bank($file, $run).'/reviews', ['kind' => 'account', 'account_key' => $account['account_key'], 'designation' => $account['designation'], 'verified' => true, 'reason_code' => 'source_verified', 'notes' => 'FICTIONAL account identity checked against the sample statement'])->assertCreated();
        }
        $this->postJson($this->bank($file, $run).'/relationships', ['account_a' => $personal['account_key'], 'account_b' => $business['account_key'], 'verified' => true, 'reason_code' => 'relationship_verified', 'notes' => 'FICTIONAL same-file transfer relationship'])->assertCreated();
        $matches = ReviewState::transfers($run->fresh());
        $proposed = collect($matches)->first(fn ($m) => $m['status'] === 'proposed' && ($m['data']['amount_minor'] ?? null) === 80000);
        $this->assertNotNull($proposed, 'Expected one proposed 800.00 transfer. Unequal candidates stay ambiguous.');
        $this->postJson($this->bank($file, $run).'/transfers/'.$proposed['id'], ['status' => 'confirmed', 'amount_minor' => 80000, 'reason_code' => 'source_verified', 'notes' => 'FICTIONAL confirmed 800.00 once'])->assertCreated();
        $calc = $this->postJson($this->bank($file, $run).'/calculate', ['account_keys' => [$personal['account_key']], 'ownership_percent' => '100.00', 'reason_code' => 'source_verified', 'notes' => 'FICTIONAL personal account only'])->assertCreated()->json();
        $this->assertSame(280000, $calc['data']['observed_deposits_minor']);
        $this->assertSame(200000, $calc['data']['eligible_minor']);
        $this->assertSame(80000, $calc['data']['excluded_minor']);
        $this->assertSame('166.67', $calc['data']['monthly_display']);

        $mismatch = $this->uploadFile($file, base_path('qa/fixtures/bank/personal-totals-mismatch-2025-03.pdf'));
        $mismatchRun = $this->processBank($file, [$mismatch], 'personal', 12, '2025-03', 'mismatch');
        $codes = array_map(fn ($f) => $f['data']['code'] ?? null, ReviewState::findings($mismatchRun));
        $this->assertContains('balance_arithmetic', $codes);
        $this->assertContains('credit_total', $codes);
        $mismatchCalc = $this->calculate($file, $mismatchRun, 'personal');
        $this->assertSame(10000, $mismatchCalc['data']['eligible_minor']);

        $missingPage = $this->uploadFile($file, base_path('qa/fixtures/bank/personal-missing-pages-2025-04.pdf'));
        $pageRun = $this->processBank($file, [$missingPage], 'personal', 12, '2025-04', 'pages');
        $pageCodes = array_map(fn ($f) => $f['data']['code'] ?? null, ReviewState::findings($pageRun));
        $this->assertContains('missing_or_reordered_pages', $pageCodes);

        $original = $this->uploadFile($file, base_path('qa/fixtures/bank/personal-12/personal-2025-01.pdf'));
        $duplicate = $this->uploadFile($file, base_path('qa/fixtures/bank/duplicate-of-personal-2025-01.pdf'));
        $dupRun = $this->processBank($file, [$original, $duplicate], 'personal', 12, '2025-01', 'duplicate');
        $this->assertSame(1, ReviewState::transactions($dupRun) ? count(array_filter(ReviewState::transactions($dupRun), fn ($t) => ($t['credit_minor'] ?? 0) > 0)) : 0);
        $dupCodes = array_map(fn ($f) => $f['data']['code'] ?? null, ReviewState::findings($dupRun));
        $this->assertContains('duplicate_original', $dupCodes);

        $scanned = $this->uploadFile($file, base_path('qa/fixtures/bank/personal-scanned-2025-01.pdf'));
        $scanRun = $this->processBank($file, [$scanned], 'personal', 12, '2025-01', 'scanned');
        $this->assertSame([], array_values(array_filter(ReviewState::transactions($scanRun), fn ($t) => ($t['credit_minor'] ?? 0) > 0)));

        $this->post('/api/files/'.$file->id.'/documents', ['file' => UploadedFile::fake()->createWithContent('not-a-statement.txt', file_get_contents(base_path('qa/fixtures/bank/not-a-statement.txt'))), 'fictional_sample' => true], ['Accept' => 'application/json'])->assertUnprocessable();
        Queue::fake();
        $corrupt = $this->uploadFile($file, base_path('qa/fixtures/bank/corrupt-truncated.pdf'));
        $failed = $this->startBank($file, [$corrupt], 'personal', 12, '2025-01', 'corrupt');
        try {
            (new ProcessBankRun($failed->id))->handle(app(Pipeline::class));
            $this->fail('Corrupt PDF should not parse');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('SAMPLE', $e->getMessage());
        }
        $this->assertSame('Failed', $failed->fresh()->status);
        $this->assertSame(0, count(ReviewState::transactions($failed)));
    }

    public function test_missing_clamav_marks_unscanned_and_appraisal_export_keeps_open_findings(): void
    {
        $this->requireParser();
        config(['bank.clamav' => '/nonexistent-clamscan', 'bank.fictional_scan_bypass' => false]);
        [$owner, $org] = $this->workspace();
        $file = $this->loanFile($org, $owner, 'appraisal');
        $this->actingAs($owner);
        $dir = base_path('qa/fixtures/appraisal');
        $versions = [];
        $classifications = [];
        foreach (glob($dir.'/*.pdf') as $path) {
            $id = $this->uploadFile($file, $path);
            $versions[] = $id;
            $name = basename($path);
            $classifications[$id] = str_contains($name, 'lease') ? 'lease' : (str_contains($name, '1007') ? 'rental_schedule' : (str_contains($name, '216') ? 'operating_income' : 'appraisal'));
        }
        $version = DocumentVersion::findOrFail($versions[0]);
        $this->assertSame('Unscanned', $version->scan_status);
        $response = $this->postJson('/api/files/'.$file->id.'/appraisal/runs', [
            'idempotency_key' => 'qa-dscr',
            'document_version_ids' => $versions,
            'classifications' => $classifications,
            'context' => ['loan_occupancy' => 'investment', 'transaction' => 'purchase', 'units' => 4, 'program' => 'FICTIONAL DSCR example'],
        ])->assertAccepted();
        $runId = $response->json('run_id');
        $run = \App\Models\Review\Run::findOrFail($runId);
        if ($run->status === 'Queued') {
            (new ProcessAppraisal($run->id))->handle(app(\App\Appraisal\Pipeline::class));
        }
        $run->refresh();
        $this->assertContains($run->status, ['NeedsReview', 'Completed']);
        $this->assertNotEmpty(Store::findings($run));
        $money = fn (string $v) => ['value' => $v, 'source' => 'FICTIONAL lease page', 'basis' => 'Reviewer selected the fictional lease rent', 'confirmed' => true];
        $units = [];
        foreach ([['1', '900.00'], ['2', '800.00'], ['3', '800.00'], ['4', '800.00']] as [$id, $rent]) {
            $units[] = ['id' => $id, 'inspection_occupancy' => 'occupied', 'current_occupancy' => 'occupied', 'lease_status' => 'executed', 'rent_method' => 'long_term', 'is_adu' => false, 'market' => $money($rent), 'qualifying' => $money($rent)];
        }
        $calc = $this->postJson('/api/files/'.$file->id.'/appraisal/runs/'.$run->id.'/inputs', [
            'notes' => 'FICTIONAL reviewer confirmed 3300 rent and 3000 payment',
            'input' => ['units' => $units, 'payment' => ['mode' => 'total', 'total' => $money('3000.00'), 'interest_only' => false], 'loan' => $money('264000.00'), 'value_basis' => $money('880000.00'), 'appraised_value' => '880000.00'],
        ])->assertCreated()->json();
        $this->assertSame('1.10', $calc['data']['qualifying_dscr']['display']);
        $this->assertSame('1.10', $calc['data']['market_dscr']['display']);
        $report = $this->postJson('/api/files/'.$file->id.'/appraisal/runs/'.$run->id.'/report', ['notes' => 'Export while findings are still open'])->assertCreated()->json();
        $artifactId = $report['id'];
        $pdf = $this->get('/api/files/'.$file->id.'/appraisal/runs/'.$run->id.'/reports/'.$artifactId.'/pdf')->assertOk()->getContent();
        $xlsx = $this->get('/api/files/'.$file->id.'/appraisal/runs/'.$run->id.'/reports/'.$artifactId.'/xlsx')->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $text = $this->pdfText($pdf);
        $this->assertStringContainsString('1.10', $text);
        $this->assertStringNotContainsString('Loan approved', $text);
        $this->assertStringNotContainsString('Fraud detected', $text);
        $this->assertStringStartsWith('PK', $xlsx);
        $this->assertNotSame('Clean', DB::table('bank_scans')->where('document_version_id', $version->id)->latest('id')->value('status'));
    }

    private function requireParser(): void
    {
        $python = (string) config('bank.python');
        if (! is_executable($python) || ! is_file(base_path('qa/fixtures/bank/personal-12/personal-2025-01.pdf'))) {
            $this->markTestSkipped('Python parser venv or qa fixtures are not present');
        }
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['bank.fictional_scan_bypass' => true, 'bank.extractor' => 'native', 'bank.integrity.provider' => 'disabled']);
        $this->app->forgetInstance(StatementExtractor::class);
        $this->assertInstanceOf(NativeExtractor::class, $this->app->make(StatementExtractor::class));
    }

    private function workspace(): array
    {
        $owner = User::factory()->create();
        $org = Organization::create(['name' => 'FICTIONAL QA Org', 'plan_id' => Plan::first()->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $org->id, 'role' => 'owner', 'org_wide_access' => true]);

        return [$owner, $org];
    }

    private function loanFile(Organization $org, User $owner, string $module): LoanFile
    {
        $borrower = Borrower::create(['organization_id' => $org->id, 'name' => 'Fictional Avery Sample']);

        return LoanFile::create(['organization_id' => $org->id, 'borrower_id' => $borrower->id, 'created_by' => $owner->id, 'name' => 'FICTIONAL '.$module.' file', 'module' => $module, 'is_demo' => true]);
    }

    private function uploadDir(LoanFile $file, string $relative): array
    {
        $ids = [];
        foreach (glob(base_path($relative.'/*.pdf')) as $path) {
            $ids[] = $this->uploadFile($file, $path);
        }
        sort($ids);

        return $ids;
    }

    private function uploadFile(LoanFile $file, string $path): int
    {
        return $this->post('/api/files/'.$file->id.'/documents', [
            'file' => UploadedFile::fake()->createWithContent(basename($path), file_get_contents($path)),
            'fictional_sample' => true,
        ], ['Accept' => 'application/json'])->assertCreated()->json('version.id');
    }

    private function processBank(LoanFile $file, array $versions, string $workflow, int $months, string $end, string $key = 'qa'): Run
    {
        $run = $this->startBank($file, $versions, $workflow, $months, $end, $key);
        if ($run->status === 'Queued') {
            (new ProcessBankRun($run->id))->handle(app(Pipeline::class));
        }

        return $run->fresh();
    }

    private function startBank(LoanFile $file, array $versions, string $workflow, int $months, string $end, string $key): Run
    {
        $rule = $this->postJson('/api/files/'.$file->id.'/bank/rules', [
            'name' => 'FICTIONAL example '.$workflow.' policy',
            'status' => 'example',
            'policy' => RuleProfile::example($workflow),
        ])->assertCreated()->json('id');
        $response = $this->postJson('/api/files/'.$file->id.'/bank/runs', [
            'idempotency_key' => $key,
            'document_version_ids' => $versions,
            'workflow' => $workflow,
            'months' => $months,
            'end_month' => $end,
            'rule_id' => $rule,
        ])->assertAccepted();

        return Run::findOrFail($response->json('run_id'));
    }

    private function bank(LoanFile $file, Run $run): string
    {
        return '/api/files/'.$file->id.'/bank/runs/'.$run->id;
    }

    private function calculate(LoanFile $file, Run $run, string $workflow): array
    {
        $accounts = ReviewState::accounts($run);
        $keys = [];
        foreach ($accounts as $account) {
            if ($account['designation'] !== $workflow) {
                continue;
            }
            $keys[] = $account['account_key'];
            $this->postJson($this->bank($file, $run).'/reviews', ['kind' => 'account', 'account_key' => $account['account_key'], 'designation' => $account['designation'], 'verified' => true, 'reason_code' => 'source_verified', 'notes' => 'FICTIONAL account checked'])->assertCreated();
        }
        $groups = [];
        foreach (ReviewState::transactions($run) as $transaction) {
            $groups[$transaction['data']['initial_category']][] = $transaction['id'];
        }
        foreach ($groups as $category => $ids) {
            $this->postJson($this->bank($file, $run).'/reviews', ['kind' => 'transaction', 'transaction_ids' => $ids, 'category' => $category, 'include' => null, 'reason_code' => 'source_verified', 'notes' => 'FICTIONAL classification checked'])->assertCreated();
        }

        return $this->postJson($this->bank($file, $run).'/calculate', [
            'account_keys' => $keys,
            'ownership_percent' => '100.00',
            'reason_code' => 'source_verified',
            'notes' => 'FICTIONAL inputs confirmed',
        ])->assertCreated()->json();
    }

    private function assertExport(LoanFile $file, Run $run, array $calc, string $income, int $months): void
    {
        $report = $this->postJson($this->bank($file, $run).'/reports', ['calculation_id' => $calc['id'], 'final' => false])->assertCreated()->json('0');
        $xlsx = $this->get($this->bank($file, $run).'/reports/'.$report['id'].'/xlsx')->assertOk()->getContent();
        $pdf = $this->get($this->bank($file, $run).'/reports/'.$report['id'].'/pdf')->assertOk()->getContent();
        $path = tempnam(sys_get_temp_dir(), 'qa-xlsx');
        file_put_contents($path, $xlsx);
        $book = IOFactory::load($path);
        $this->assertSame($months, $book->getActiveSheet()->getCell('B7')->getValue());
        $this->assertEquals((float) $income, (float) $book->getActiveSheet()->getCell('B10')->getValue());
        $book->disconnectWorksheets();
        unlink($path);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $text = $this->pdfText($pdf);
        $this->assertStringContainsString($income, $text);
        $this->assertStringNotContainsString('Fraud detected', $text);
        $this->assertStringNotContainsString('Loan approved', $text);
    }

    private function pdfText(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'qa-pdf');
        file_put_contents($path, $bytes);
        $process = new Process([config('bank.python'), '-c', 'import pymupdf,sys; d=pymupdf.open(sys.argv[1]); print("".join(p.get_text("text") for p in d))', $path]);
        $process->run();
        unlink($path);
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return $process->getOutput();
    }
}
