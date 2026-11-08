<?php

namespace Tests\Feature;

use App\Contracts\DocumentExtraction;
use App\Contracts\MalwareScanner;
use App\Jobs\LoadSampleAnalysis;
use App\Models\AnalysisRun;
use App\Models\Borrower;
use App\Models\DocumentVersion;
use App\Models\FileShare;
use App\Models\Invitation;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\SampleData;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PhaseOneTest extends TestCase
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
        $b = Borrower::create(['organization_id' => $this->org->id, 'name' => 'Fictional Borrower']);
        $this->file = LoanFile::create(['organization_id' => $this->org->id, 'borrower_id' => $b->id, 'created_by' => $this->owner->id, 'name' => 'Fictional Review', 'is_demo' => true]);
    }

    private function member(string $role = 'broker', ?Organization $org = null): User
    {
        $u = User::factory()->create();
        Membership::create(['organization_id' => ($org ?? $this->org)->id, 'user_id' => $u->id, 'role' => $role, 'org_wide_access' => false]);

        return $u;
    }

    private function share(User $u): FileShare
    {
        return FileShare::create(['organization_id' => $this->org->id, 'loan_file_id' => $this->file->id, 'user_id' => $u->id, 'shared_by' => $this->owner->id]);
    }

    private function upload(string $text = ''): array
    {
        $pdf = SampleData::pdf().$text;
        $r = $this->actingAs($this->owner)->post('/api/files/'.$this->file->id.'/documents', ['file' => UploadedFile::fake()->createWithContent('fictional.pdf', $pdf), 'fictional_sample' => true], ['Accept' => 'application/json']);
        $r->assertCreated();
        DB::table('bank_scans')->insert(['organization_id' => $this->org->id, 'document_version_id' => $r->json('version.id'), 'sha256' => $r->json('version.sha256'), 'status' => 'Clean', 'adapter' => 'TEST MOCK — no malware accuracy claim', 'data' => '{}', 'created_at' => now()]);

        return $r->json();
    }

    private function sample(): void
    {
        $this->actingAs($this->owner)->postJson('/api/files/'.$this->file->id.'/sample-analysis', ['idempotency_key' => 'test-v1'])->assertAccepted();
    }

    public function test_postgresql_is_the_integration_database(): void
    {
        $this->assertSame('pgsql', DB::getDriverName());
    }

    public function test_registration_creates_organization_and_explicit_owner_grant(): void
    {
        Notification::fake();
        $r = $this->postJson('/api/auth/register', ['name' => 'Sample Owner', 'organization_name' => 'Sample Workspace', 'email' => 'new@example.test', 'password' => 'DevelopmentOnly123', 'password_confirmation' => 'DevelopmentOnly123', 'platform_admin' => true]);
        $r->assertCreated();
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertFalse($user->platform_admin);
        $this->assertDatabaseHas('memberships', ['user_id' => $user->id, 'role' => 'owner', 'org_wide_access' => true]);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->getJson('/api/files')->assertForbidden();
    }

    public function test_session_login_logout_and_wrong_password(): void
    {
        $this->postJson('/api/auth/login', ['email' => $this->owner->email, 'password' => 'incorrect'])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => $this->owner->email, 'password' => 'password'])->assertOk();
        $this->getJson('/api/me')->assertOk();
        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->assertGuest();
    }

    public function test_email_verification_uses_signed_link_and_unverified_gate(): void
    {
        $this->owner->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->owner)->getJson('/api/files')->assertForbidden();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $this->owner->id, 'hash' => sha1($this->owner->email)]);
        $this->get($url)->assertRedirect('/?verified=1');
        $this->assertNotNull($this->owner->fresh()->email_verified_at);
        $this->getJson('/api/files')->assertOk();
    }

    public function test_password_reset_sends_notification_and_resets_password(): void
    {
        Notification::fake();
        $this->postJson('/api/auth/forgot-password', ['email' => $this->owner->email])->assertOk();
        $token = null;
        Notification::assertSentTo($this->owner, ResetPassword::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        $this->postJson('/api/auth/reset-password', ['token' => $token, 'email' => $this->owner->email, 'password' => 'NewLocalPassword123', 'password_confirmation' => 'NewLocalPassword123'])->assertOk();
        $this->assertTrue(Hash::check('NewLocalPassword123', $this->owner->fresh()->password));
        $this->postJson('/api/auth/reset-password', ['token' => $token, 'email' => $this->owner->email, 'password' => 'AnotherPassword123', 'password_confirmation' => 'AnotherPassword123'])->assertUnprocessable();
    }

    public function test_profile_email_change_requires_reverification_and_password_requires_current(): void
    {
        Notification::fake();
        $this->actingAs($this->owner)->putJson('/api/auth/user/profile-information', ['name' => 'Edited', 'email' => 'edited@example.test'])->assertOk();
        $this->assertNull($this->owner->fresh()->email_verified_at);
        $this->getJson('/api/files')->assertForbidden();
        $this->putJson('/api/auth/user/password', ['current_password' => 'wrong', 'password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123'])->assertUnprocessable();
    }

    public function test_totp_setup_confirmation_and_login_gate_and_recovery_code_consumption(): void
    {
        $this->actingAs($this->owner)->postJson('/api/auth/user/two-factor-authentication')->assertStatus(423);
        $this->postJson('/api/auth/user/confirm-password', ['password' => 'password'])->assertCreated();
        $this->postJson('/api/auth/user/two-factor-authentication')->assertOk();
        $u = $this->owner->fresh();
        $this->assertNotNull($u->two_factor_secret);
        $this->assertNull($u->two_factor_confirmed_at);
        $this->getJson('/api/auth/user/two-factor-qr-code')->assertOk();
        $codes = $this->getJson('/api/auth/user/two-factor-recovery-codes')->assertOk()->json();
        $secret = decrypt($u->two_factor_secret);
        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->postJson('/api/auth/user/confirmed-two-factor-authentication', ['code' => $code])->assertOk();
        $this->assertNotNull($u->fresh()->two_factor_confirmed_at);
        $this->postJson('/api/auth/logout')->assertNoContent();
        $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'password'])->assertJson(['two_factor' => true]);
        $this->assertGuest();
        $this->getJson('/api/files')->assertUnauthorized();
        $this->postJson('/api/auth/two-factor-challenge', ['recovery_code' => $codes[0]])->assertNoContent();
        $this->assertAuthenticatedAs($u);
        $this->assertNotContains($codes[0], $u->fresh()->recoveryCodes());
    }

    public function test_cross_organization_and_unshared_members_denied(): void
    {
        $u = $this->member();
        $this->actingAs($u)->getJson('/api/files/'.$this->file->id)->assertForbidden();
        $this->getJson('/api/files')->assertJsonCount(0, 'data');
        $other = Organization::create(['name' => 'Other', 'plan_id' => Plan::first()->id]);
        $outsider = $this->member('admin', $other);
        $this->actingAs($outsider)->getJson('/api/files/'.$this->file->id)->assertForbidden();
        $this->getJson('/api/organizations/'.$this->org->id)->assertForbidden();
    }

    public function test_share_and_revoke_deny_subsequent_file_source_report_and_export(): void
    {
        $upload = $this->upload();
        $this->sample();
        $u = $this->member();
        $s = $this->share($u);
        $v = $upload['version']['id'];
        $this->actingAs($u)->getJson('/api/files/'.$this->file->id)->assertOk();
        $this->get('/api/document-versions/'.$v.'/content')->assertForbidden();
        $this->getJson('/api/files/'.$this->file->id.'/reports/bank')->assertForbidden();
        $this->actingAs($this->owner)->deleteJson('/api/files/'.$this->file->id.'/shares/'.$s->id)->assertNoContent();
        $this->actingAs($u)->getJson('/api/files/'.$this->file->id)->assertForbidden();
        $this->getJson('/api/document-versions/'.$v.'/content')->assertForbidden();
        $this->getJson('/api/document-versions/'.$v.'/content?download=1')->assertForbidden();
        $this->getJson('/api/files/'.$this->file->id.'/reports/bank')->assertForbidden();
        $this->getJson('/api/files/'.$this->file->id.'/export')->assertForbidden();
    }

    public function test_cross_org_share_and_borrower_link_rejected(): void
    {
        $other = Organization::create(['name' => 'Other', 'plan_id' => Plan::first()->id]);
        $u = $this->member('underwriter', $other);
        $this->actingAs($this->owner)->postJson('/api/files/'.$this->file->id.'/shares', ['user_id' => $u->id])->assertUnprocessable();
        $b = Borrower::create(['name' => 'Other borrower', 'organization_id' => $other->id]);
        $this->postJson('/api/organizations/'.$this->org->id.'/files', ['name' => 'Bad file', 'module' => 'bank', 'borrower_id' => $b->id, 'is_demo' => false])->assertUnprocessable();
    }

    public function test_invitation_is_email_bound_single_use_expiring_and_same_org_admin_only(): void
    {
        $u = $this->member();
        $this->actingAs($u)->postJson('/api/organizations/'.$this->org->id.'/invitations', ['email' => 'new@example.test', 'role' => 'admin'])->assertForbidden();
        $token = 'fictional-invitation-token';
        $i = Invitation::create(['id' => (string) Str::uuid(), 'organization_id' => $this->org->id, 'email' => 'invitee@example.test', 'role' => 'broker', 'token_hash' => hash('sha256', $token), 'invited_by' => $this->owner->id, 'expires_at' => now()->addDay()]);
        $this->postJson('/api/invitations/accept', ['token' => $token])->assertForbidden();
        $invitee = User::factory()->create(['email' => 'invitee@example.test']);
        $this->actingAs($invitee)->postJson('/api/invitations/accept', ['token' => $token])->assertCreated();
        $this->assertDatabaseHas('memberships', ['organization_id' => $this->org->id, 'user_id' => $invitee->id, 'role' => 'broker', 'org_wide_access' => false]);
        $this->getJson('/api/files/'.$this->file->id)->assertForbidden();
        $this->postJson('/api/invitations/accept', ['token' => $token])->assertGone();
    }

    public function test_invite_creation_uses_configured_mail_and_revocation(): void
    {
        Mail::fake();
        $r = $this->actingAs($this->owner)->postJson('/api/organizations/'.$this->org->id.'/invitations', ['email' => 'new@example.test', 'role' => 'broker'])->assertCreated();
        $this->assertArrayNotHasKey('token', $r->json());
        $i = Invitation::findOrFail($r->json('id'));
        $this->assertSame(64, strlen($i->token_hash));
        $this->deleteJson('/api/organizations/'.$this->org->id.'/invitations/'.$i->id)->assertNoContent();
        $this->assertNotNull($i->fresh()->revoked_at);
    }

    public function test_expired_invite_cannot_be_accepted(): void
    {
        $u = User::factory()->create();
        $i = Invitation::create(['id' => (string) Str::uuid(), 'organization_id' => $this->org->id, 'email' => $u->email, 'role' => 'processor', 'token_hash' => hash('sha256', 'expired'), 'invited_by' => $this->owner->id, 'expires_at' => now()->subMinute()]);
        $this->actingAs($u)->postJson('/api/invitations/accept', ['token' => 'expired'])->assertGone();
    }

    public function test_broker_redaction_all_surfaces_and_direct_restricted_routes(): void
    {
        $this->sample();
        $u = $this->member('broker');
        $this->share($u);
        $this->actingAs($this->owner)->postJson('/api/files/'.$this->file->id.'/notes', ['body' => 'RESTRICTED INTERNAL SECRET', 'visibility' => 'internal'])->assertCreated();
        $this->postJson('/api/files/'.$this->file->id.'/overrides', ['target_id' => 'bank-1', 'kind' => 'finding', 'value' => 'resolved', 'reason' => 'Fictional review decision'])->assertCreated();
        $this->actingAs($u);
        foreach (['/api/files/'.$this->file->id, '/api/files/'.$this->file->id.'/analysis', '/api/files/'.$this->file->id.'/activity', '/api/files?q=fraud'] as $url) {
            $json = $this->getJson($url)->assertOk()->getContent();
            foreach (['typeface inconsistency', 'deposit_trend', 'sustainability', 'RESTRICTED INTERNAL SECRET', 'reviewer.override'] as $secret) {
                $this->assertStringNotContainsString($secret, $json);
            }
        }
        $this->getJson('/api/files/'.$this->file->id.'/analysis/restricted/integrity')->assertForbidden();
        $this->getJson('/api/files/'.$this->file->id.'/analysis/restricted/sustainability')->assertForbidden();
        $this->getJson('/api/files/'.$this->file->id.'/review-history')->assertForbidden();
    }

    public function test_broker_cannot_override_reclassify_manage_files_or_elevate_via_sharing(): void
    {
        $this->sample();
        $u = $this->member('broker');
        $this->share($u);
        $this->actingAs($u);
        foreach (['classification', 'finding'] as $kind) {
            $this->postJson('/api/files/'.$this->file->id.'/overrides', ['kind' => $kind, 'target_id' => 'tx-1', 'value' => 'eligible_deposit', 'reason' => 'Trying to bypass role'])->assertForbidden();
        }
        $this->patchJson('/api/files/'.$this->file->id, ['name' => 'Bad rename', 'role' => 'admin'])->assertForbidden();
        $this->postJson('/api/files/'.$this->file->id.'/shares', ['user_id' => $this->owner->id])->assertForbidden();
        $this->postJson('/api/files/'.$this->file->id.'/notes', ['body' => 'Restricted', 'visibility' => 'internal'])->assertForbidden();
    }

    public function test_role_downgrade_after_share_immediately_removes_reviewer_capability(): void
    {
        $u = $this->member('underwriter');
        $this->share($u);
        $this->sample();
        $this->actingAs($u)->getJson('/api/files/'.$this->file->id.'/analysis/restricted/integrity')->assertOk();
        Membership::where('user_id', $u->id)->where('organization_id', $this->org->id)->update(['role' => 'broker']);
        $this->getJson('/api/files/'.$this->file->id.'/analysis/restricted/integrity')->assertForbidden();
        $this->postJson('/api/files/'.$this->file->id.'/overrides', ['kind' => 'classification', 'target_id' => 'tx-1', 'value' => 'eligible_deposit', 'reason' => 'Previously reviewer'])->assertForbidden();
    }

    public function test_platform_admin_has_no_document_bypass_and_horizon_is_private_even_local(): void
    {
        $r = $this->upload();
        $u = User::factory()->create(['platform_admin' => true]);
        $this->actingAs($u)->getJson('/api/platform')->assertOk();
        $this->getJson('/api/files/'.$this->file->id)->assertForbidden();
        $this->getJson('/api/document-versions/'.$r['version']['id'].'/content')->assertForbidden();
        $this->actingAs($this->owner)->getJson('/api/platform')->assertForbidden();
        $this->get('/horizon')->assertForbidden();
    }

    public function test_replacement_adds_version_preserves_original_bytes_checksum_and_audit(): void
    {
        $a = $this->upload();
        $pdf = SampleData::pdf();
        $new = $pdf."\n% replacement";
        $r = $this->post('/api/documents/'.$a['document_id'].'/versions', ['file' => UploadedFile::fake()->createWithContent('revision.pdf', $new), 'fictional_sample' => true], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(2, $r->json('version.version'));
        $v1 = DocumentVersion::findOrFail($a['version']['id']);
        $v2 = DocumentVersion::findOrFail($r->json('version.id'));
        $this->assertSame($pdf, Storage::disk('private')->get($v1->storage_key));
        $this->assertSame(hash('sha256', $pdf), $v1->sha256);
        $this->assertNotSame($v1->storage_key, $v2->storage_key);
        $this->get('/api/document-versions/'.$v1->id.'/content?download=1')->assertOk()->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertDatabaseHas('audit_events', ['action' => 'document.version_added', 'loan_file_id' => $this->file->id]);
        $this->assertSame('Pending', $v1->scan_status);
    }

    public function test_immutable_versions_and_audit_enforced_in_database(): void
    {
        $r = $this->upload();
        $this->expectException(QueryException::class);
        DB::table('document_versions')->where('id', $r['version']['id'])->update(['sha256' => str_repeat('0', 64)]);
    }

    public function test_audit_cannot_be_deleted_in_database(): void
    {
        $this->upload();
        $this->expectException(QueryException::class);
        DB::table('audit_events')->delete();
    }

    public function test_invalid_mime_extension_size_rejected_and_nonfictional_upload_stays_quarantined(): void
    {
        $this->actingAs($this->owner);
        $url = '/api/files/'.$this->file->id.'/documents';
        $this->post($url, ['file' => UploadedFile::fake()->createWithContent('fake.pdf', '<?php echo 123;'), 'fictional_sample' => true], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($url, ['file' => UploadedFile::fake()->createWithContent('fake.exe', SampleData::pdf()), 'fictional_sample' => true], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($url, ['file' => UploadedFile::fake()->create('huge.pdf', 20481, 'application/pdf'), 'fictional_sample' => true], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($url, ['file' => UploadedFile::fake()->createWithContent('sample.pdf', SampleData::pdf())], ['Accept' => 'application/json'])->assertCreated();
        $this->assertDatabaseCount('document_versions', 1);
        $this->getJson('/api/document-versions/'.DocumentVersion::first()->id.'/content')->assertStatus(423);
    }

    public function test_unauthorized_document_preview_and_exports_require_file_policy(): void
    {
        $r = $this->upload();
        $u = $this->member('underwriter');
        $this->actingAs($u)->getJson('/api/document-versions/'.$r['version']['id'].'/content')->assertForbidden();
        $this->getJson('/api/files/'.$this->file->id.'/reports/bank')->assertForbidden();
        $this->getJson('/api/files/'.$this->file->id.'/export')->assertForbidden();
    }

    public function test_plan_file_member_storage_limits_are_enforced(): void
    {
        $plan = $this->org->plan;
        $plan->update(['file_limit' => 1, 'member_limit' => 1, 'storage_limit_bytes' => 10]);
        $this->actingAs($this->owner)->postJson('/api/organizations/'.$this->org->id.'/files', ['name' => 'Over limit', 'module' => 'bank', 'borrower_id' => $this->file->borrower_id, 'is_demo' => true])->assertUnprocessable();
        $this->postJson('/api/organizations/'.$this->org->id.'/invitations', ['email' => 'over@example.test', 'role' => 'broker'])->assertUnprocessable();
        $this->post('/api/files/'.$this->file->id.'/documents', ['file' => UploadedFile::fake()->createWithContent('sample.pdf', SampleData::pdf()), 'fictional_sample' => true], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_sample_analysis_is_explicit_idempotent_and_never_runs_on_real_file(): void
    {
        $this->upload();
        $this->assertDatabaseCount('analysis_runs', 0);
        $this->sample();
        $this->sample();
        $this->assertDatabaseCount('analysis_runs', 1);
        $this->assertDatabaseCount('analysis_snapshots', 1);
        $this->assertDatabaseCount('report_snapshots', 1);
        $this->getJson('/api/files/'.$this->file->id.'/analysis')->assertJsonPath('analysis.data.dscr.scenarios.0.dscr', '1.10')->assertJsonPath('analysis.data.dscr.scenarios.1.dscr', '0.95');
        $this->file->update(['is_demo' => false]);
        $this->postJson('/api/files/'.$this->file->id.'/sample-analysis', ['idempotency_key' => 'not-demo'])->assertUnprocessable();
    }

    public function test_all_module_report_previews_work_with_open_findings_and_no_fake_download(): void
    {
        $this->sample();
        foreach (['bank', 'appraisal', 'dscr'] as $module) {
            $this->getJson('/api/files/'.$this->file->id.'/reports/'.$module)->assertOk()->assertJsonPath('downloads', 'Not available in Phase 1.');
        }
        $this->getJson('/api/files/'.$this->file->id.'/export')->assertStatus(501);
    }

    public function test_overrides_have_history_and_do_not_mutate_sample_snapshot(): void
    {
        $this->sample();
        $before = DB::table('analysis_snapshots')->value('data');
        $this->postJson('/api/files/'.$this->file->id.'/overrides', ['kind' => 'classification', 'target_id' => 'tx-1', 'value' => 'excluded_non_income', 'reason' => 'Fictional documented decision'])->assertCreated();
        $this->getJson('/api/files/'.$this->file->id.'/review-history')->assertJsonCount(1);
        $this->assertSame($before, DB::table('analysis_snapshots')->value('data'));
        $this->assertDatabaseHas('audit_events', ['action' => 'reviewer.override', 'visibility' => 'internal']);
    }

    public function test_draft_inputs_persist_without_calculating_and_reject_sensitive_keys(): void
    {
        $this->actingAs($this->owner)->patchJson('/api/files/'.$this->file->id, ['draft_inputs' => ['statement_months' => 24, 'account_type' => 'business', 'rent' => '3300.00', 'pitia' => '3000.00', 'appraisal_form' => '1025', 'units' => 2, 'occupancy' => 'investment']])->assertOk()->assertJsonPath('draft_inputs.statement_months', 24);
        $this->assertDatabaseCount('analysis_runs', 0);
        $this->patchJson('/api/files/'.$this->file->id, ['draft_inputs' => ['fraud_override' => 'clear']])->assertUnprocessable();
    }

    public function test_member_removal_and_invitation_boundary_do_not_leave_grants(): void
    {
        $u = $this->member();
        $s = $this->share($u);
        $m = Membership::where('user_id', $u->id)->where('organization_id', $this->org->id)->firstOrFail();
        $this->actingAs($this->owner)->deleteJson('/api/organizations/'.$this->org->id.'/members/'.$m->id)->assertNoContent();
        $this->actingAs($u)->getJson('/api/files/'.$this->file->id)->assertForbidden();
        $this->assertDatabaseMissing('file_shares', ['id' => $s->id]);
    }

    public function test_branding_update_requires_org_admin_and_borrower_update_requires_access(): void
    {
        $u = $this->member('broker');
        $this->share($u);
        $this->actingAs($u)->patchJson('/api/organizations/'.$this->org->id, ['name' => 'Bad change'])->assertForbidden();
        $this->patchJson('/api/organizations/'.$this->org->id.'/borrowers/'.$this->file->borrower_id, ['name' => 'Bad borrower'])->assertForbidden();
        $this->actingAs($this->owner)->patchJson('/api/organizations/'.$this->org->id, ['name' => 'New Brand', 'branding' => ['product_name' => 'Lens Review', 'accent' => '#128877'], 'preferences' => ['timezone' => 'Asia/Dhaka', 'currency' => 'USD']])->assertOk();
    }

    public function test_admin_role_requires_explicit_organization_wide_grant(): void
    {
        $u = $this->member('admin');
        $this->actingAs($u)->getJson('/api/files/'.$this->file->id)->assertForbidden();
        $membership = Membership::where('organization_id', $this->org->id)->where('user_id', $u->id)->firstOrFail();
        $this->actingAs($this->owner)->patchJson('/api/organizations/'.$this->org->id.'/members/'.$membership->id, ['role' => 'admin', 'org_wide_access' => true])->assertOk();
        $this->actingAs($u)->getJson('/api/files/'.$this->file->id)->assertOk();
        $this->actingAs($this->owner)->patchJson('/api/organizations/'.$this->org->id.'/members/'.$membership->id, ['role' => 'broker', 'org_wide_access' => true])->assertUnprocessable();
    }

    public function test_invited_admin_has_no_implicit_organization_file_access(): void
    {
        $u = User::factory()->create();
        $token = 'admin-invite';
        Invitation::create(['id' => (string) Str::uuid(), 'organization_id' => $this->org->id, 'email' => $u->email, 'role' => 'admin', 'token_hash' => hash('sha256', $token), 'invited_by' => $this->owner->id, 'expires_at' => now()->addDay()]);
        $this->actingAs($u)->postJson('/api/invitations/accept', ['token' => $token])->assertCreated();
        $this->getJson('/api/files/'.$this->file->id)->assertForbidden();
    }

    public function test_database_composite_foreign_key_blocks_cross_tenant_borrower_link(): void
    {
        $other = Organization::create(['name' => 'Other', 'plan_id' => Plan::first()->id]);
        $borrower = Borrower::create(['name' => 'Other borrower', 'organization_id' => $other->id]);
        $this->expectException(QueryException::class);
        DB::table('loan_files')->insert(['organization_id' => $this->org->id, 'borrower_id' => $borrower->id, 'created_by' => $this->owner->id, 'name' => 'Invalid link']);
    }

    public function test_tampered_email_verification_signature_is_rejected(): void
    {
        $this->owner->forceFill(['email_verified_at' => null])->save();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $this->owner->id, 'hash' => sha1($this->owner->email)]);
        $this->actingAs($this->owner)->get($url.'tampered')->assertForbidden();
        $this->assertNull($this->owner->fresh()->email_verified_at);
    }

    public function test_document_storage_keys_and_disks_never_return_in_api(): void
    {
        $r = $this->upload();
        $this->assertArrayNotHasKey('storage_key', $r['version']);
        $this->assertArrayNotHasKey('disk', $r['version']);
        $file = $this->getJson('/api/files/'.$this->file->id)->assertOk()->json();
        $this->assertArrayNotHasKey('storage_key', $file['documents'][0]['versions'][0]);
        $this->assertArrayNotHasKey('disk', $file['documents'][0]['versions'][0]);
    }

    public function test_rulesets_cannot_be_read_across_organizations(): void
    {
        $this->sample();
        $other = Organization::create(['name' => 'Other', 'plan_id' => Plan::first()->id]);
        $u = $this->member('owner', $other);
        $this->actingAs($u)->getJson('/api/rulesets?organization_id='.$this->org->id)->assertForbidden();
        $this->getJson('/api/rulesets?organization_id='.$other->id)->assertOk()->assertJsonCount(0, 'rulesets');
    }

    public function test_disabled_integrations_fail_closed_and_scanner_never_reports_clean(): void
    {
        $scanner = app(MalwareScanner::class)->scan(['sha256' => 'fictional']);
        $this->assertSame('not_configured', $scanner['status']);
        $this->assertNull($scanner['clean']);
        $this->expectException(\LogicException::class);
        app(DocumentExtraction::class)->extract([]);
    }

    public function test_login_throttle_is_enforced(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => $this->owner->email, 'password' => 'incorrect'])->assertUnprocessable();
        }
        $this->postJson('/api/auth/login', ['email' => $this->owner->email, 'password' => 'incorrect'])->assertTooManyRequests();
    }

    public function test_upload_is_read_only_after_archive_but_originals_remain(): void
    {
        $upload = $this->upload();
        $this->patchJson('/api/files/'.$this->file->id, ['archived' => true])->assertOk();
        $this->post('/api/files/'.$this->file->id.'/documents', ['file' => UploadedFile::fake()->createWithContent('sample.pdf', SampleData::pdf()), 'fictional_sample' => true], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->get('/api/document-versions/'.$upload['version']['id'].'/content')->assertOk();
        $this->assertDatabaseCount('document_versions', 1);
    }

    public function test_organization_audit_and_usage_are_admin_only_and_file_grant_scoped(): void
    {
        $this->upload();
        $u = $this->member('admin');
        $this->actingAs($u)->getJson('/api/organizations/'.$this->org->id.'/audit')->assertOk()->assertJsonCount(0);
        $this->share($u);
        $this->getJson('/api/organizations/'.$this->org->id.'/audit')->assertOk()->assertJsonFragment(['action' => 'document.uploaded']);
        $this->getJson('/api/organizations/'.$this->org->id.'/usage')->assertOk()->assertJsonPath('files', 1);
        $broker = $this->member();
        $this->share($broker);
        $this->actingAs($broker)->getJson('/api/organizations/'.$this->org->id.'/audit')->assertForbidden();
        $this->getJson('/api/organizations/'.$this->org->id.'/usage')->assertForbidden();
    }

    public function test_pending_invitations_reserve_member_entitlement_seats(): void
    {
        $this->org->plan->update(['member_limit' => 2]);
        Mail::fake();
        $this->actingAs($this->owner)->postJson('/api/organizations/'.$this->org->id.'/invitations', ['email' => 'seat1@example.test', 'role' => 'broker'])->assertCreated();
        $this->postJson('/api/organizations/'.$this->org->id.'/invitations', ['email' => 'seat2@example.test', 'role' => 'broker'])->assertUnprocessable();
    }

    public function test_actual_queue_job_redelivery_preserves_single_snapshot_report_and_source(): void
    {
        $this->sample();
        $run = AnalysisRun::firstOrFail();
        $job = new LoadSampleAnalysis($run->id, $this->owner->id);
        $job->handle();
        $job->handle();
        $this->assertDatabaseCount('analysis_snapshots', 1);
        $this->assertDatabaseCount('report_snapshots', 1);
        $this->assertDatabaseCount('document_versions', 1);
        $this->assertSame(1, $run->fresh()->attempts);
    }
}
