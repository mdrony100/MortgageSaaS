<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Borrower;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\FileShare;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;
use App\Services\Audit;
use App\Services\SampleData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceAndSupportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('private');
        $this->seed();
        config(['platform.console_require_2fa' => true, 'bank.fictional_scan_bypass' => false, 'bank.clamav' => '/nonexistent-clamscan']);
        $this->owner = User::factory()->create();
        $this->org = Organization::create(['name' => 'Fictional Lending', 'plan_id' => Plan::first()->id]);
        Membership::create(['organization_id' => $this->org->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'org_wide_access' => true]);
    }

    public function test_overview_counts_only_files_and_documents_the_user_can_see(): void
    {
        $bank = $this->file('Bank file', 'bank');
        $appraisal = $this->file('Appraisal file', 'appraisal', 'in_review');
        $this->file('Finished file', 'dscr', 'complete');
        $this->file('Archived file', 'bank', 'draft', true);
        $this->version($bank, 'statement.pdf');
        $this->version($appraisal, 'appraisal.pdf');
        DB::table('bank_runs')->insert(['id' => (string) Str::uuid(), 'organization_id' => $this->org->id, 'loan_file_id' => $bank->id, 'requested_by' => $this->owner->id, 'idempotency_key' => 'run-1', 'status' => 'Extracting', 'stage' => 'Extracting', 'attempts' => 1, 'options' => '{}', 'input_hash' => str_repeat('a', 64), 'cancel_requested' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('fraud_findings')->insert(['organization_id' => $this->org->id, 'loan_file_id' => $appraisal->id, 'data' => '{"title":"Flag"}', 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()]);
        Audit::record('document.uploaded', $this->org->id, $bank->id, [], 'shared', $this->owner->id);
        Audit::record('note.added', $this->org->id, $bank->id, [], 'internal', $this->owner->id);

        $other = Organization::create(['name' => 'Other Lending', 'plan_id' => Plan::first()->id]);
        $otherUser = User::factory()->create();
        Membership::create(['organization_id' => $other->id, 'user_id' => $otherUser->id, 'role' => 'owner', 'org_wide_access' => true]);
        $secret = LoanFile::create(['organization_id' => $other->id, 'borrower_id' => Borrower::create(['organization_id' => $other->id, 'name' => 'Hidden Borrower'])->id, 'created_by' => $otherUser->id, 'name' => 'Secret Other File', 'module' => 'bank']);

        $broker = User::factory()->create();
        Membership::create(['organization_id' => $this->org->id, 'user_id' => $broker->id, 'role' => 'broker', 'org_wide_access' => false]);
        FileShare::create(['organization_id' => $this->org->id, 'loan_file_id' => $bank->id, 'user_id' => $broker->id, 'shared_by' => $this->owner->id]);

        $owner = $this->actingAs($this->owner)->getJson('/api/organizations/'.$this->org->id.'/overview')->assertOk();
        $owner->assertJsonPath('stats.loan_files', 3)
            ->assertJsonPath('stats.in_progress', 1)
            ->assertJsonPath('stats.needing_review', 1)
            ->assertJsonPath('stats.completed', 1)
            ->assertJsonPath('stats.documents', 2)
            ->assertJsonPath('stats.flags', 1)
            ->assertJsonPath('stats.archived', 1)
            ->assertJsonPath('modules.bank', 1)
            ->assertJsonPath('modules.appraisal', 1)
            ->assertJsonPath('can_create', true);
        $this->assertSame('appraisal.pdf', $owner->json('appraisals.0.name'));
        $this->assertSame('statement.pdf', $owner->json('statements.0.name'));
        $this->assertTrue(collect($owner->json('activity'))->contains(fn ($row) => $row['label'] === 'Note added'));
        $owner->assertDontSee('Secret Other File')->assertDontSee('Hidden Borrower');

        $limited = $this->actingAs($broker)->getJson('/api/organizations/'.$this->org->id.'/overview')->assertOk();
        $limited->assertJsonPath('stats.loan_files', 1)
            ->assertJsonPath('stats.documents', 1)
            ->assertJsonPath('stats.flags', 0)
            ->assertJsonPath('can_create', false)
            ->assertJsonPath('appraisals', [])
            ->assertJsonPath('statements.0.loan_file_id', $bank->id);
        $this->assertFalse(collect($limited->json('activity'))->contains(fn ($row) => $row['label'] === 'Note added'));
        $limited->assertDontSee('Appraisal file')->assertDontSee('Secret Other File');

        $this->actingAs($otherUser)->getJson('/api/organizations/'.$this->org->id.'/overview')->assertForbidden();
        $this->actingAs($this->owner)->getJson('/api/organizations/'.$other->id.'/overview')->assertForbidden();
        $this->assertSame($secret->name, 'Secret Other File');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/organizations/'.$this->org->id.'/overview')->assertUnauthorized();
    }

    public function test_suspended_organization_cannot_open_the_overview(): void
    {
        $this->org->update(['suspended_at' => now()]);
        $this->actingAs($this->owner)->getJson('/api/organizations/'.$this->org->id.'/overview')->assertStatus(423);
    }

    public function test_create_loan_file_page_is_served_and_creation_still_lands_on_the_file(): void
    {
        $this->actingAs($this->owner)->get('/files/create')->assertOk();
        $borrower = Borrower::create(['organization_id' => $this->org->id, 'name' => 'Sample Borrower']);
        $created = $this->postJson('/api/organizations/'.$this->org->id.'/files', ['name' => 'New file', 'borrower_id' => $borrower->id, 'module' => 'bank', 'is_demo' => true])->assertCreated();
        $this->assertSame($this->org->id, $created->json('organization_id'));
        $created->assertJsonPath('is_demo', false);
        $this->assertFalse((bool) LoanFile::find($created->json('id'))->is_demo);
        $viewer = User::factory()->create();
        Membership::create(['organization_id' => $this->org->id, 'user_id' => $viewer->id, 'role' => 'viewer', 'org_wide_access' => false]);
        $this->actingAs($viewer)->postJson('/api/organizations/'.$this->org->id.'/files', ['name' => 'Nope', 'borrower_id' => $borrower->id, 'module' => 'bank', 'is_demo' => true])->assertForbidden();
    }

    public function test_support_tickets_are_isolated_audited_and_mail_failure_does_not_fail_the_request(): void
    {
        config(['website.inquiry_notify_email' => 'ops@example.test']);
        Mail::shouldReceive('raw')->andThrow(new \RuntimeException('smtp down'));

        $created = $this->actingAs($this->owner)->post('/api/organizations/'.$this->org->id.'/support-tickets', [
            'subject' => 'Cannot open a statement',
            'category' => 'documents',
            'priority' => 'high',
            'message' => 'The upload stays on the loan file page.',
            'file' => UploadedFile::fake()->createWithContent('note.pdf', SampleData::pdf()),
        ], ['Accept' => 'application/json'])->assertCreated();
        $id = $created->json('ticket.id');
        $this->assertSame('Unscanned', $created->json('ticket.messages.0.attachments.0.scan_status'));
        $this->assertNotEmpty(SupportTicketAttachment::first()->storage_key);
        $this->assertTrue(AuditEvent::where('action', 'support_ticket.created')->where('organization_id', $this->org->id)->where('metadata->ticket_id', $id)->exists());

        $viewer = User::factory()->create();
        Membership::create(['organization_id' => $this->org->id, 'user_id' => $viewer->id, 'role' => 'viewer', 'org_wide_access' => false]);
        $this->actingAs($viewer)->getJson('/api/organizations/'.$this->org->id.'/support-tickets')->assertOk()->assertJsonCount(0, 'tickets.data');
        $this->actingAs($viewer)->getJson('/api/organizations/'.$this->org->id.'/support-tickets/'.$id)->assertForbidden();
        $attachment = SupportTicketAttachment::first();
        $this->actingAs($viewer)->get('/api/support-ticket-attachments/'.$attachment->id.'/content')->assertForbidden();

        $other = Organization::create(['name' => 'Other Lending', 'plan_id' => Plan::first()->id]);
        $otherUser = User::factory()->create();
        Membership::create(['organization_id' => $other->id, 'user_id' => $otherUser->id, 'role' => 'owner', 'org_wide_access' => true]);
        $this->actingAs($otherUser)->getJson('/api/organizations/'.$this->org->id.'/support-tickets')->assertForbidden();
        $this->actingAs($otherUser)->getJson('/api/organizations/'.$other->id.'/support-tickets/'.$id)->assertNotFound();
        $this->actingAs($otherUser)->get('/api/support-ticket-attachments/'.$attachment->id.'/content')->assertForbidden();

        $this->actingAs($this->owner)->get('/api/support-ticket-attachments/'.$attachment->id.'/content')->assertOk()->assertHeader('X-Scan-Status', 'Unscanned');

        $admin = $this->admin();
        $this->actingAs($viewer)->getJson('/api/admin/support-tickets')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/admin/support-tickets?status=open')->assertOk()->assertJsonPath('tickets.data.0.id', $id);
        $this->actingAs($admin)->getJson('/api/admin/support-tickets?status=closed')->assertOk()->assertJsonCount(0, 'tickets.data');
        $this->actingAs($admin)->patchJson('/api/admin/support-tickets/'.$id, ['status' => 'pending'])->assertOk()->assertJsonPath('ticket.status', 'pending');
        $this->assertTrue(AuditEvent::where('action', 'support_ticket.status_changed')->where('metadata->to', 'pending')->exists());
        $this->actingAs($admin)->postJson('/api/admin/support-tickets/'.$id.'/replies', ['message' => 'We can see the ticket.'])->assertCreated();
        $thread = $this->actingAs($this->owner)->getJson('/api/organizations/'.$this->org->id.'/support-tickets/'.$id)->assertOk();
        $this->assertTrue(collect($thread->json('ticket.messages'))->contains(fn ($message) => $message['staff'] === true && $message['body'] === 'We can see the ticket.'));

        $this->actingAs($admin)->patchJson('/api/admin/support-tickets/'.$id, ['status' => 'closed'])->assertOk();
        $this->actingAs($this->owner)->post('/api/organizations/'.$this->org->id.'/support-tickets/'.$id.'/replies', ['message' => 'It is still happening.'], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('ticket.status', 'open');
    }

    public function test_rejected_support_attachment_is_not_stored(): void
    {
        $dir = sys_get_temp_dir().'/ticket-clam-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/clamscan', "#!/bin/sh\nexit 1\n");
        chmod($dir.'/clamscan', 0755);
        config(['bank.clamav' => $dir.'/clamscan']);
        $this->actingAs($this->owner)->post('/api/organizations/'.$this->org->id.'/support-tickets', [
            'subject' => 'Bad file',
            'category' => 'technical',
            'priority' => 'normal',
            'message' => 'Please look.',
            'file' => UploadedFile::fake()->createWithContent('note.pdf', SampleData::pdf()),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertSame(0, SupportTicket::count());
        $this->assertSame(0, SupportTicketAttachment::count());
    }

    private function file(string $name, string $module, string $status = 'draft', bool $archived = false): LoanFile
    {
        $borrower = Borrower::create(['organization_id' => $this->org->id, 'name' => $name.' borrower']);

        return LoanFile::create(['organization_id' => $this->org->id, 'borrower_id' => $borrower->id, 'created_by' => $this->owner->id, 'name' => $name, 'module' => $module, 'status' => $status, 'is_demo' => true, 'archived_at' => $archived ? now() : null]);
    }

    private function version(LoanFile $file, string $name): void
    {
        $document = Document::create(['organization_id' => $file->organization_id, 'loan_file_id' => $file->id, 'title' => $name, 'category' => 'source']);
        DocumentVersion::create(['organization_id' => $file->organization_id, 'document_id' => $document->id, 'uploaded_by' => $this->owner->id, 'version' => 1, 'disk' => 'private', 'storage_key' => 'quarantine/test/'.Str::uuid(), 'original_name' => $name, 'mime' => 'application/pdf', 'bytes' => 128, 'sha256' => hash('sha256', $name), 'validation_status' => 'accepted_quarantine', 'scan_status' => 'Unscanned']);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'staff@example.test']);
        $user->forceFill(['platform_admin' => true, 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }
}
