<?php

namespace Tests\Feature;

use App\Bank\DocumentParser;
use App\Bank\ParserConfigurationException;
use App\Bank\Pipeline;
use App\Jobs\ProcessBankRun;
use App\Models\Bank\Run;
use App\Models\Borrower;
use App\Models\DocumentVersion;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\SampleData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParserSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_sandbox_python_or_pymupdf_is_a_customer_safe_configuration_failure(): void
    {
        $parser = app(DocumentParser::class);
        config(['bank.python' => '']);
        $report = $parser->diagnose();
        $this->assertFalse($report['ok']);
        $this->assertContains('Python path is empty.', $report['issues']);

        $dir = sys_get_temp_dir().'/parser-old-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/python', "#!/bin/sh\nif echo \"$*\" | grep -q pymupdf; then exit 1; fi\necho 3.6.8\n");
        chmod($dir.'/python', 0755);
        config(['bank.python' => $dir.'/python']);
        $old = $parser->diagnose();
        $this->assertFalse($old['ok']);
        $this->assertStringContainsString('older than 3.10', implode(' ', $old['issues']));

        $env = app()->environment();
        app()->detectEnvironment(fn () => 'production');
        config(['bank.sandbox' => null, 'bank.python' => $dir.'/python']);
        try {
            $parser->parse($dir.'/missing.pdf', $dir.'/out');
            $this->fail('Production without bubblewrap selected must not parse.');
        } catch (ParserConfigurationException $e) {
            $this->assertSame(ParserConfigurationException::CUSTOMER_MESSAGE, $e->getMessage());
            $this->assertStringNotContainsString('sandbox', strtolower($e->getMessage()));
            $this->assertStringNotContainsString('bwrap', $e->getMessage());
            $this->assertStringNotContainsString('Python', $e->getMessage());
        } finally {
            app()->detectEnvironment(fn () => $env);
        }

        config(['bank.sandbox' => 'none']);
        $optOut = $parser->diagnose();
        $this->assertNotNull($optOut['warning']);
        $this->assertStringContainsString('sandbox is off', $optOut['warning']);
        $this->assertStringNotContainsString('not selected', implode(' ', $optOut['issues']));
    }

    public function test_every_version_is_scanned_before_a_parser_configuration_failure_and_the_job_does_not_retry(): void
    {
        Storage::fake('private');
        $this->seed();
        $owner = User::factory()->create();
        $org = Organization::create(['name' => 'Fictional Lending', 'plan_id' => Plan::first()->id]);
        Membership::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'owner', 'org_wide_access' => true]);
        $borrower = Borrower::create(['organization_id' => $org->id, 'name' => 'Fictional Borrower']);
        $file = LoanFile::create(['organization_id' => $org->id, 'borrower_id' => $borrower->id, 'created_by' => $owner->id, 'name' => 'Fictional Review', 'module' => 'bank', 'is_demo' => true]);
        $scanner = sys_get_temp_dir().'/parser-clam-'.bin2hex(random_bytes(4));
        mkdir($scanner);
        file_put_contents($scanner.'/clamscan', "#!/bin/sh\nexit 127\n");
        chmod($scanner.'/clamscan', 0755);
        config(['bank.clamav' => $scanner.'/clamscan', 'bank.fictional_scan_bypass' => false, 'bank.python' => '', 'bank.integrity.provider' => 'disabled']);

        $this->actingAs($owner);
        $ids = [];
        foreach (['one.pdf', 'two.pdf'] as $name) {
            $ids[] = $this->post('/api/files/'.$file->id.'/documents', [
                'file' => UploadedFile::fake()->createWithContent($name, SampleData::pdf()),
                'fictional_sample' => true,
            ], ['Accept' => 'application/json'])->assertCreated()->json('version.id');
        }
        foreach ($ids as $id) {
            $this->assertSame('Pending', DocumentVersion::findOrFail($id)->scan_status);
        }

        Queue::fake();
        $runId = $this->postJson('/api/files/'.$file->id.'/bank/runs', [
            'idempotency_key' => 'parser-setup',
            'document_version_ids' => $ids,
            'workflow' => 'personal',
            'months' => 12,
            'end_month' => '2025-12',
        ])->assertAccepted()->json('run_id');

        (new ProcessBankRun($runId))->handle(app(Pipeline::class));

        $run = Run::findOrFail($runId);
        $this->assertSame('Failed', $run->status);
        $this->assertSame(1, $run->attempts);
        $this->assertSame(ParserConfigurationException::CUSTOMER_MESSAGE, $run->error_code);
        foreach ($ids as $id) {
            $this->assertSame('Unscanned', DocumentVersion::findOrFail($id)->fresh()->scan_status);
        }
    }
}
