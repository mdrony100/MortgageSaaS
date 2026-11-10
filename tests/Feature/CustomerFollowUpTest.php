<?php

namespace Tests\Feature;

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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerFollowUpTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $org;

    private LoanFile $file;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed();
        $this->owner = User::factory()->create();
        $this->org = Organization::create(['name' => 'Fictional Lending', 'plan_id' => Plan::first()->id]);
        Membership::create(['organization_id' => $this->org->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'org_wide_access' => true]);
        $borrower = Borrower::create(['organization_id' => $this->org->id, 'name' => 'Fictional Borrower']);
        $this->file = LoanFile::create(['organization_id' => $this->org->id, 'borrower_id' => $borrower->id, 'created_by' => $this->owner->id, 'name' => 'Fictional Review', 'module' => 'bank', 'is_demo' => true]);
    }

    public function test_underwriter_wide_grant_sees_files_and_other_roles_cannot_hold_it(): void
    {
        $reviewer = User::factory()->create();
        Membership::create(['organization_id' => $this->org->id, 'user_id' => $reviewer->id, 'role' => 'underwriter', 'org_wide_access' => false]);
        $this->actingAs($reviewer)->getJson('/api/files?organization_id='.$this->org->id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/files/'.$this->file->id)->assertForbidden();

        $membership = Membership::where('user_id', $reviewer->id)->firstOrFail();
        $this->actingAs($this->owner)->patchJson('/api/organizations/'.$this->org->id.'/members/'.$membership->id, ['role' => 'underwriter', 'org_wide_access' => true])->assertOk();
        $this->actingAs($reviewer)->getJson('/api/files?organization_id='.$this->org->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/files/'.$this->file->id)->assertOk();

        $this->actingAs($this->owner)->postJson('/api/organizations/'.$this->org->id.'/invitations', ['email' => 'reviewer@example.test', 'role' => 'underwriter', 'org_wide_access' => true])->assertCreated();
        $this->postJson('/api/organizations/'.$this->org->id.'/invitations', ['email' => 'processor@example.test', 'role' => 'processor', 'org_wide_access' => true])->assertUnprocessable();
        $this->postJson('/api/organizations/'.$this->org->id.'/invitations', ['email' => 'broker@example.test', 'role' => 'broker', 'org_wide_access' => true])->assertUnprocessable();

        $processor = User::factory()->create();
        $processorMembership = Membership::create(['organization_id' => $this->org->id, 'user_id' => $processor->id, 'role' => 'processor', 'org_wide_access' => false]);
        $this->patchJson('/api/organizations/'.$this->org->id.'/members/'.$processorMembership->id, ['role' => 'processor', 'org_wide_access' => true])->assertUnprocessable();
        $this->actingAs($processor)->getJson('/api/files/'.$this->file->id)->assertForbidden();
    }

    public function test_failed_scan_is_stored_on_the_document_version(): void
    {
        $dir = sys_get_temp_dir().'/qa-clam-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/clamscan', "#!/bin/sh\nexit 2\n");
        chmod($dir.'/clamscan', 0755);
        config(['bank.clamav' => $dir.'/clamscan', 'bank.fictional_scan_bypass' => false, 'bank.integrity.provider' => 'disabled']);

        $this->actingAs($this->owner);
        $uploaded = $this->post('/api/files/'.$this->file->id.'/documents', [
            'file' => UploadedFile::fake()->createWithContent('FICTIONAL.pdf', SampleData::pdf()),
            'fictional_sample' => true,
        ], ['Accept' => 'application/json'])->assertCreated();
        $versionId = $uploaded->json('version.id');
        $this->assertSame('Pending', DocumentVersion::findOrFail($versionId)->scan_status);

        Queue::fake();
        $runId = $this->postJson('/api/files/'.$this->file->id.'/bank/runs', [
            'idempotency_key' => 'scan-writeback',
            'document_version_ids' => [$versionId],
            'workflow' => 'personal',
            'months' => 12,
            'end_month' => '2025-12',
        ])->assertAccepted()->json('run_id');
        try {
            (new ProcessBankRun($runId))->handle(app(Pipeline::class));
            $this->fail('A scanner exit other than clean or infected should stop the run.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ScanFailed', $e->getMessage());
        }

        $this->assertSame('ScanFailed', DocumentVersion::findOrFail($versionId)->fresh()->scan_status);
        $this->assertSame('Failed', Run::findOrFail($runId)->status);
        $this->expectException(QueryException::class);
        DB::table('document_versions')->where('id', $versionId)->update(['sha256' => str_repeat('a', 64)]);
    }
}
