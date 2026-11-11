<?php

namespace Tests\Feature;

use App\Bank\DocumentParser;
use App\Bank\FindingLocation;
use App\Bank\Pipeline;
use App\Bank\Providers\NativeExtractor;
use App\Models\Bank\Run;
use App\Models\Borrower;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FindingLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_line_is_located_and_an_ambiguous_phrase_is_not(): void
    {
        $page = ['lines' => [
            ['text' => 'Payroll deposit 4000.00', 'region' => [0.1, 0.2, 0.5, 0.02]],
            ['text' => 'Rent payment 1500.00', 'region' => [0.1, 0.24, 0.4, 0.02]],
        ], 'words' => [
            ['text' => 'Payroll', 'region' => [0.1, 0.2, 0.12, 0.02]],
            ['text' => 'deposit', 'region' => [0.24, 0.2, 0.14, 0.02]],
        ]];
        $this->assertSame([0.1, 0.2, 0.5, 0.02], FindingLocation::locate($page, 'Payroll deposit'));
        $page['lines'][] = ['text' => 'Another payroll deposit', 'region' => [0.1, 0.4, 0.4, 0.02]];
        $this->assertNull(FindingLocation::locate($page, 'Payroll deposit'));
        $wordsOnly = ['words' => $page['words']];
        $box = FindingLocation::locate($wordsOnly, 'Payroll deposit');
        $this->assertSame([0.1, 0.2, 0.28, 0.02], $box);
        $points = FindingLocation::columns(['page' => 1, 'document_version_id' => 4, 'region' => ['x' => 40, 'y' => 80, 'width' => 520, 'height' => 180]]);
        $this->assertSame(1, $points['source_page']);
        $this->assertSame(4, $points['source_document_version_id']);
        $this->assertNull($points['bbox_x']);
    }

    public function test_fixture_parser_emits_page_and_coordinates_and_findings_store_them(): void
    {
        $this->seed();
        $owner = User::factory()->create();
        $org = Organization::create(['name' => 'FICTIONAL location tests', 'plan_id' => Plan::first()->id]);
        Membership::create(['user_id' => $owner->id, 'organization_id' => $org->id, 'role' => 'owner', 'org_wide_access' => true]);
        $borrower = Borrower::create(['organization_id' => $org->id, 'name' => 'Fictional Avery Sample']);
        $file = LoanFile::create(['organization_id' => $org->id, 'borrower_id' => $borrower->id, 'created_by' => $owner->id, 'name' => 'Sample Street refinance', 'is_demo' => true, 'module' => 'bank']);
        $run = Run::create(['id' => (string) Str::uuid(), 'organization_id' => $org->id, 'loan_file_id' => $file->id, 'requested_by' => $owner->id, 'idempotency_key' => 'loc-'.Str::uuid(), 'status' => 'Analyzing', 'stage' => 'Analyzing', 'options' => [], 'input_hash' => str_repeat('a', 64)]);
        $path = base_path('qa/fixtures/bank/personal-12/personal-2025-01.pdf');
        $this->assertFileExists($path);
        $dir = sys_get_temp_dir().'/ml-find-'.Str::uuid();
        mkdir($dir, 0700);
        try {
            $manifest = app(DocumentParser::class)->parse($path, $dir.'/out');
            $page = $manifest['pages'][0];
            $this->assertSame(1, $page['number']);
            $this->assertNotEmpty($page['words']);
            $this->assertNotEmpty($page['lines']);
            $this->assertCount(4, $page['words'][0]['region']);
            $box = FindingLocation::locate($page, 'Payroll deposit');
            $this->assertNotNull($box);
            $this->assertCount(4, $box);
            $extracted = (new NativeExtractor)->extract($manifest, $run, 1, $dir.'/out');
            $this->assertNotEmpty($extracted['transactions']);
            $this->assertSame(1, $extracted['transactions'][0]['page']);
            $saved = app(Pipeline::class)->finding($run, 'fixture-payroll', ['category' => 'integrity_indicator', 'code' => 'payroll_deposit', 'observation' => 'Fictional payroll deposit needs a person to check it', 'document_version_id' => 1, 'page' => 1, 'region' => $box, 'region_verified' => true]);
            $this->assertSame(1, (int) $saved['source_page']);
            $this->assertSame(1, (int) $saved['source_document_version_id']);
            $this->assertNotNull($saved['bbox_x']);
            $this->assertNotNull($saved['bbox_h']);
            $pageOnly = app(Pipeline::class)->finding($run, 'fixture-page-only', ['category' => 'missing_evidence', 'code' => 'missing_or_reordered_pages', 'observation' => 'Confirm the page order', 'document_version_id' => 1, 'page' => 1]);
            $this->assertSame(1, (int) $pageOnly['source_page']);
            $this->assertNull($pageOnly['bbox_x']);
            $presented = FindingLocation::present($saved);
            $this->assertSame(1, $presented['page']);
            $this->assertNotNull($presented['bbox']);
            $this->assertTrue($presented['bbox']['normalized']);
        } finally {
            foreach (glob($dir.'/out/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($dir.'/out')) {
                rmdir($dir.'/out');
            }
            rmdir($dir);
        }
        $this->actingAs($owner)->getJson('/api/files?organization_id='.$org->id)->assertOk()
            ->assertJsonPath('data.0.document_count', 0)
            ->assertJsonPath('data.0.latest_run_status', 'Analyzing')
            ->assertJsonPath('data.0.has_report', false);
    }
}
