<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ReportController;
use App\Jobs\SendPlatformEmail;
use App\Models\AuditEvent;
use App\Models\Borrower;
use App\Models\ImpersonationSession;
use App\Models\Invitation;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlatformRequest;
use App\Models\User;
use App\Models\WebsiteInquiry;
use App\Services\SampleData;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerConsoleTest extends TestCase
{
    use RefreshDatabase;

    private const GET_ENDPOINTS = ['me', 'overview', 'analytics', 'revenue', 'plans', 'system', 'settings', 'organizations', 'organizations/{org}', 'users', 'impersonations', 'approvals', 'audit', 'reports', 'reports/users?format=csv', 'inquiries', 'inquiries/{inquiry}'];

    private User $owner;

    private Organization $org;

    private LoanFile $file;

    private WebsiteInquiry $inquiry;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed();
        config(['platform.console_require_2fa' => true, 'platform.impersonation.enabled' => true, 'platform.impersonation.minutes' => 30]);
        $this->owner = User::factory()->create(['name' => 'Fictional Owner', 'email' => 'owner@example.test', 'password' => Hash::make('Owner-password-123')]);
        $this->org = Organization::create(['name' => 'Fictional Lending', 'plan_id' => Plan::where('name', 'Starter')->first()->id]);
        Membership::create(['organization_id' => $this->org->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'org_wide_access' => true]);
        $b = Borrower::create(['organization_id' => $this->org->id, 'name' => 'Zebulon Quixote Borrower', 'email' => 'zebulon.borrower@example.test']);
        $this->file = LoanFile::create(['organization_id' => $this->org->id, 'borrower_id' => $b->id, 'created_by' => $this->owner->id, 'name' => 'Confidential Loan File Q7', 'is_demo' => true]);
        $this->inquiry = WebsiteInquiry::create(['name' => 'Fictional Prospect', 'email' => 'prospect@example.test', 'company' => 'Example Lending Co', 'inquiry_type' => 'demo', 'message' => 'Please show us the workflow.']);
    }

    private function admin(bool $twoFactor = true, array $attrs = []): User
    {
        $u = User::factory()->create(['name' => 'Fictional Platform Admin'] + $attrs);
        $u->forceFill(['platform_admin' => true] + ($twoFactor ? ['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()] : []))->save();

        return $u;
    }

    private function member(string $role, ?Organization $org = null): User
    {
        $u = User::factory()->create();
        Membership::create(['organization_id' => ($org ?? $this->org)->id, 'user_id' => $u->id, 'role' => $role, 'org_wide_access' => in_array($role, ['owner', 'admin'], true)]);

        return $u;
    }

    private function url(string $path): string
    {
        return '/api/admin/'.str_replace(['{org}', '{inquiry}'], [$this->org->id, $this->inquiry->id], $path);
    }

    /** Simulates the next HTTP request rebuilding guards from the session (as in production). */
    private function nextRequest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function writes(): array
    {
        return [
            ['post', 'organizations/{org}/actions', ['action' => 'change_plan', 'plan_id' => Plan::where('name', 'Team')->first()->id]],
            ['post', 'organizations/{org}/notes', ['body' => 'x']],
            ['post', 'users/'.$this->owner->id.'/actions', ['action' => 'disable']],
            ['post', 'approvals/999/decision', ['decision' => 'approve']],
            ['patch', 'inquiries/{inquiry}', ['status' => 'closed']],
            ['post', 'inquiries/{inquiry}/notes', ['body' => 'x']],
            ['post', 'inquiries/{inquiry}/convert', ['organization_name' => 'X', 'plan_id' => 1]],
            ['post', 'impersonation', ['user_id' => $this->owner->id, 'reason' => 'Testing authorization rules']],
            ['put', 'settings', ['website_contact_phone' => '555-0100']],
            ['post', 'settings/test', ['check' => 'clamav']],
            ['post', 'settings/test-email', []],
        ];
    }

    public function test_every_console_endpoint_rejects_guests_org_roles_unverified_disabled_and_admins_without_2fa(): void
    {
        foreach (self::GET_ENDPOINTS as $p) {
            $this->getJson($this->url($p))->assertUnauthorized();
        }
        foreach ($this->writes() as [$m, $p, $body]) {
            $this->json($m, $this->url($p), $body)->assertUnauthorized();
        }
        $cases = ['owner' => $this->owner];
        foreach (['admin', 'underwriter', 'processor', 'broker', 'viewer'] as $role) {
            $cases[$role] = $this->member($role);
        }
        $cases['unverified platform admin'] = $this->admin(true, ['email_verified_at' => null]);
        $cases['platform admin without 2FA'] = $this->admin(false);
        foreach ($cases as $label => $user) {
            foreach (self::GET_ENDPOINTS as $p) {
                $this->actingAs($user)->getJson($this->url($p))->assertForbidden();
            }
            foreach ($this->writes() as [$m, $p, $body]) {
                $this->actingAs($user)->json($m, $this->url($p), $body)->assertForbidden();
            }
        }
        $this->actingAs($cases['platform admin without 2FA'])->getJson($this->url('overview'))->assertJsonPath('code', 'two_factor_required');
        $disabled = $this->admin(true, ['disabled_at' => now()]);
        $this->actingAs($disabled)->getJson($this->url('overview'))->assertUnauthorized()->assertJsonPath('code', 'account_disabled');
        $this->assertNull($this->owner->fresh()->disabled_at);
        $this->assertSame('new', $this->inquiry->fresh()->status);
        $this->assertSame(0, ImpersonationSession::count());

        $admin = $this->admin();
        foreach (self::GET_ENDPOINTS as $p) {
            $this->actingAs($admin)->getJson($this->url($p))->assertOk();
        }
        config(['platform.console_require_2fa' => false]);
        $this->actingAs($cases['platform admin without 2FA'])->getJson($this->url('overview'))->assertOk();
    }

    public function test_console_pages_have_their_own_gated_shell_and_old_urls_redirect(): void
    {
        $this->get('/admin')->assertRedirect('/login?redirect=%2Fadmin');
        $this->get('/admin/organizations')->assertRedirect('/login?redirect=%2Fadmin%2Forganizations');
        $denied = $this->actingAs($this->owner)->get('/admin/users')->assertForbidden()->assertSee('Not authorized');
        $this->assertStringNotContainsString('id="admin"', $denied->getContent());
        $this->actingAs($this->admin(false))->get('/admin')->assertForbidden()->assertSee('Two-factor sign-in required')->assertSee('/settings/profile');
        $this->actingAs($this->admin(true, ['email_verified_at' => null]))->get('/admin')->assertRedirect('/verify-email');
        $ok = $this->actingAs($this->admin())->get('/admin/organizations/1')->assertOk()->assertSee('Owner Console')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('id="admin"', $ok->getContent());
        $this->assertStringNotContainsString('<div id="app"></div>', $ok->getContent());
        $this->get('/platform')->assertRedirect('/admin');
        $this->get('/platform/inquiries?id=7')->assertRedirect('/admin/inquiries?id=7');
        $this->getJson('/api/platform')->assertNotFound();
    }

    public function test_organization_actions_change_subscription_limits_and_notes_with_audit(): void
    {
        $admin = $this->admin();
        $team = Plan::where('name', 'Team')->first();
        $this->actingAs($admin);
        $act = fn (array $body) => $this->postJson($this->url('organizations/{org}/actions'), $body);
        $act(['action' => 'change_plan', 'plan_id' => $team->id])->assertOk()->assertJsonPath('organization.plan.name', 'Team');
        $act(['action' => 'change_plan', 'plan_id' => 99999])->assertUnprocessable();
        $act(['action' => 'set_limits', 'file_limit' => 400, 'member_limit' => null, 'storage_gb' => 2])->assertOk()->assertJsonPath('limits.files', 400)->assertJsonPath('limits.members', $team->member_limit)->assertJsonPath('limits.storage_bytes', 2 * 1073741824);
        $act(['action' => 'extend_trial', 'days' => 10])->assertOk()->assertJsonPath('organization.subscription_status', 'trial');
        $this->assertTrue($this->org->fresh()->trial_ends_at->between(now()->addDays(9), now()->addDays(11)));
        $act(['action' => 'end_trial', 'status' => 'past_due'])->assertOk()->assertJsonPath('organization.subscription_status', 'past_due');
        $act(['action' => 'end_trial', 'status' => 'trial'])->assertUnprocessable();
        $act(['action' => 'set_status', 'status' => 'active'])->assertOk()->assertJsonPath('organization.subscription_status', 'active');
        $act(['action' => 'suspend'])->assertUnprocessable();
        $act(['action' => 'suspend', 'reason' => 'Unpaid invoices (fictional)'])->assertOk()->assertJsonPath('organization.suspension_reason', 'Unpaid invoices (fictional)');
        $this->assertNotNull($this->org->fresh()->suspended_at);
        $act(['action' => 'reactivate'])->assertOk()->assertJsonPath('organization.suspended_at', null)->assertJsonPath('organization.subscription_status', 'active');
        $this->postJson($this->url('organizations/{org}/notes'), ['body' => 'Called the owner about renewal.'])->assertCreated()->assertJsonPath('notes.0.body', 'Called the owner about renewal.');
        foreach (['change_plan', 'set_limits', 'extend_trial', 'end_trial', 'set_status', 'suspend', 'reactivate'] as $a) {
            $e = AuditEvent::where('action', 'admin.organization.'.$a)->latest('id')->firstOrFail();
            $this->assertSame($admin->id, $e->actor_id, $a);
            $this->assertSame($this->org->id, $e->organization_id, $a);
        }
        $this->assertEquals(['from' => Plan::where('name', 'Starter')->first()->id, 'to' => $team->id], AuditEvent::where('action', 'admin.organization.change_plan')->first()->metadata['changes']['plan_id']);
        $this->assertTrue(AuditEvent::where('action', 'admin.organization.note_added')->where('actor_id', $admin->id)->exists());
        // Limit overrides are enforced by the existing entitlement checks.
        $this->org->update(['file_limit_override' => 1]);
        $this->actingAs($this->owner)->postJson('/api/organizations/'.$this->org->id.'/files', ['name' => 'Another fictional file', 'module' => 'bank'])->assertUnprocessable();
    }

    public function test_suspended_and_pending_organizations_are_blocked_for_members_but_not_the_console(): void
    {
        $broker = $this->member('broker');
        $this->actingAs($this->owner)->getJson('/api/organizations/'.$this->org->id)->assertOk();
        $this->actingAs($this->admin())->postJson($this->url('organizations/{org}/actions'), ['action' => 'suspend', 'reason' => 'Fictional suspension'])->assertOk();
        foreach ([$this->owner, $broker] as $u) {
            $this->actingAs($u)->getJson('/api/organizations/'.$this->org->id)->assertStatus(423)->assertJsonPath('code', 'organization_suspended');
            $this->actingAs($u)->getJson('/api/files/'.$this->file->id)->assertStatus(423);
            $this->actingAs($u)->getJson('/api/organizations/'.$this->org->id.'/billing')->assertStatus(423);
        }
        $this->actingAs($this->owner)->getJson('/api/files')->assertOk()->assertJsonMissing(['name' => 'Confidential Loan File Q7']);
        $this->actingAs($this->owner)->getJson('/api/me')->assertOk();
        $this->actingAs($this->admin())->getJson($this->url('organizations/{org}'))->assertOk();
        $this->org->update(['suspended_at' => null, 'subscription_status' => 'active', 'approval_status' => 'pending']);
        $this->actingAs($this->owner)->getJson('/api/organizations/'.$this->org->id)->assertStatus(423)->assertJsonPath('code', 'organization_pending');
        $this->org->update(['approval_status' => 'approved']);
        $this->actingAs($this->owner)->getJson('/api/files/'.$this->file->id)->assertOk();
    }

    public function test_disabling_sign_in_blocks_login_and_ends_existing_sessions(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson($this->url('users/'.$admin->id.'/actions'), ['action' => 'disable'])->assertUnprocessable();
        $other = $this->admin();
        $this->actingAs($admin)->postJson($this->url('users/'.$other->id.'/actions'), ['action' => 'disable'])->assertUnprocessable();
        $this->actingAs($admin)->postJson($this->url('users/'.$this->owner->id.'/actions'), ['action' => 'disable', 'reason' => 'Left the company'])->assertOk()->assertJsonPath('user.disabled_at', fn ($v) => $v !== null);
        $this->assertTrue(AuditEvent::where('action', 'admin.user.disabled')->where('actor_id', $admin->id)->where('metadata->target_user_id', $this->owner->id)->exists());
        $this->actingAs($this->owner->fresh())->getJson('/api/me')->assertUnauthorized()->assertJsonPath('code', 'account_disabled');
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'owner@example.test', 'password' => 'Owner-password-123'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest('web');
        $this->postJson('/api/auth/login', ['email' => 'owner@example.test', 'password' => 'wrong-password'])->assertUnprocessable();
        $this->actingAs($admin)->postJson($this->url('users/'.$this->owner->id.'/actions'), ['action' => 'enable'])->assertOk()->assertJsonPath('user.disabled_at', null);
        $this->app['auth']->forgetGuards();
        $this->app['auth']->guard('web')->logout();
        $this->postJson('/api/auth/login', ['email' => 'owner@example.test', 'password' => 'Owner-password-123'])->assertOk();
        $this->assertNotNull($this->owner->fresh()->last_login_at);
    }

    public function test_resend_verification_and_password_reset_use_existing_flows_and_never_expose_passwords(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($admin)->postJson($this->url('users/'.$unverified->id.'/actions'), ['action' => 'resend_verification'])->assertOk();
        Notification::assertSentTo($unverified, VerifyEmail::class);
        $this->postJson($this->url('users/'.$this->owner->id.'/actions'), ['action' => 'resend_verification'])->assertUnprocessable();
        $this->postJson($this->url('users/'.$this->owner->id.'/actions'), ['action' => 'send_password_reset'])->assertOk();
        Notification::assertSentTo($this->owner, ResetPassword::class);
        $this->postJson($this->url('users/'.$this->owner->id.'/actions'), ['action' => 'set_password', 'password' => 'x'])->assertUnprocessable();
        $this->postJson($this->url('users/'.$this->owner->id.'/actions'), ['action' => 'grant_platform_admin'])->assertUnprocessable();
        $this->assertFalse($this->owner->fresh()->platform_admin);
        $users = $this->getJson($this->url('users'))->assertOk()->getContent();
        $this->assertStringNotContainsString('password', strtolower($users));
        $this->assertStringNotContainsString('two_factor_secret', $users);
        $this->assertTrue(AuditEvent::where('action', 'admin.user.password_reset_sent')->exists());
        $this->assertTrue(AuditEvent::where('action', 'admin.user.verification_resent')->exists());
    }

    public function test_platform_admin_cli_command_grants_and_revokes_with_audit(): void
    {
        $this->artisan('mortgage:platform-admin', ['email' => 'OWNER@example.test'])->assertExitCode(0);
        $this->assertTrue($this->owner->fresh()->platform_admin);
        $this->assertTrue(AuditEvent::where('action', 'platform.admin_granted')->where('metadata->target_user_id', $this->owner->id)->exists());
        $this->artisan('mortgage:platform-admin', ['email' => 'owner@example.test', '--revoke' => true])->assertExitCode(0);
        $this->assertFalse($this->owner->fresh()->platform_admin);
        $this->assertTrue(AuditEvent::where('action', 'platform.admin_revoked')->exists());
        $this->artisan('mortgage:platform-admin', ['email' => 'nobody@example.test'])->assertExitCode(1);
    }

    public function test_console_responses_and_reports_never_contain_documents_borrowers_or_secrets(): void
    {
        config(['billing.webhook_secret' => 'whsec_FICTIONAL_SECRET_VALUE', 'bank.openai.key' => 'sk-FICTIONAL-SECRET-KEY', 'billing.provider' => 'fake']);
        $r = $this->actingAs($this->owner)->post('/api/files/'.$this->file->id.'/documents', ['file' => UploadedFile::fake()->createWithContent('Zebulon-statement-original.pdf', SampleData::pdf()), 'fictional_sample' => true], ['Accept' => 'application/json'])->assertCreated();
        $admin = $this->admin();
        $this->actingAs($admin);
        $bodies = '';
        foreach (self::GET_ENDPOINTS as $p) {
            $bodies .= $this->getJson($this->url($p))->assertOk()->getContent();
        }
        foreach (array_keys(ReportController::TYPES) as $type) {
            $bodies .= $this->get($this->url('reports/'.$type.'?format=csv'))->assertOk()->streamedContent();
        }
        foreach (['Zebulon', 'zebulon.borrower', 'Confidential Loan File Q7', 'Zebulon-statement-original', $r->json('version.sha256'), 'storage_key', 'whsec_FICTIONAL_SECRET_VALUE', 'sk-FICTIONAL-SECRET-KEY', 'two_factor_secret', 'provisional_monthly_price'] as $needle) {
            $this->assertStringNotContainsString((string) $needle, $bodies, $needle);
        }
        $this->assertStringContainsString('only be seen inside an audited, time-limited support session', $bodies);
        // Platform staff still have no document bypass outside a support session.
        $this->getJson('/api/files/'.$this->file->id)->assertForbidden();
        $this->getJson('/api/document-versions/'.$r->json('version.id').'/content')->assertForbidden();
    }

    public function test_reports_export_csv_and_xlsx_with_date_filters_and_audit(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $csv = $this->get($this->url('reports/organizations?format=csv'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('Subscription status', $csv);
        $this->assertStringContainsString('Fictional Lending', $csv);
        $none = $this->get($this->url('reports/organizations?format=csv&from=2000-01-01&to=2000-01-31'))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Fictional Lending', $none);
        $x = $this->get($this->url('reports/usage?format=xlsx'))->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $x->headers->get('Content-Type'));
        $this->assertStringStartsWith('PK', $x->streamedContent());
        WebsiteInquiry::create(['name' => '=HYPERLINK("x")', 'email' => 'f@example.test', 'company' => 'Formula Co', 'inquiry_type' => 'demo', 'message' => 'x']);
        $this->assertStringContainsString("'=HYPERLINK", $this->get($this->url('reports/inquiries?format=csv'))->streamedContent());
        $rev = $this->get($this->url('reports/revenue?format=csv'))->streamedContent();
        $this->assertStringContainsString('Pricing not configured', $rev);
        $this->get($this->url('reports/bogus?format=csv'))->assertNotFound();
        $this->getJson($this->url('reports/users?format=pdf'))->assertUnprocessable();
        $this->assertSame(5, AuditEvent::where('action', 'admin.report.exported')->where('actor_id', $admin->id)->count());
    }

    public function test_revenue_shows_pricing_not_configured_until_approved_prices_exist(): void
    {
        $this->actingAs($this->admin());
        $this->org->update(['subscription_status' => 'active']);
        $this->getJson($this->url('revenue'))->assertOk()->assertJsonPath('pricing_configured', false)->assertJsonPath('mrr', null)->assertJsonPath('arr', null)->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Pricing not configured'));
        $starter = Plan::where('name', 'Starter')->first();
        $other = Organization::create(['name' => 'Second Fictional Org', 'plan_id' => $starter->id, 'subscription_status' => 'active']);
        Organization::create(['name' => 'Trial Org', 'plan_id' => $starter->id, 'subscription_status' => 'trial']);
        config(['billing.catalog' => [$starter->id => ['monthly_price' => '100.00']], 'billing.provider' => 'fake']);
        DB::table('billing_subscriptions')->insert(['organization_id' => $other->id, 'provider' => 'fake', 'provider_reference' => (string) Str::uuid(), 'status' => 'active', 'data' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson($this->url('revenue'))->assertOk()->assertJsonPath('pricing_configured', true)->assertJsonPath('mrr', 200)->assertJsonPath('arr', 2400)->assertJsonPath('test_data', true)->assertJsonPath('subscriptions.0.provider', 'fake');
    }

    public function test_analytics_uses_real_seeded_counts(): void
    {
        $this->actingAs($this->admin());
        LoanFile::create(['organization_id' => $this->org->id, 'borrower_id' => $this->file->borrower_id, 'created_by' => $this->owner->id, 'name' => 'Fictional appraisal', 'module' => 'appraisal', 'is_demo' => true]);
        WebsiteInquiry::create(['name' => 'Spam Bot', 'email' => 's@example.test', 'company' => 'Spam', 'inquiry_type' => 'demo', 'message' => 'x', 'is_spam' => true]);
        $d = $this->getJson($this->url('analytics?from='.now()->subDays(10)->toDateString().'&to='.now()->toDateString()))->assertOk()->json();
        $sum = fn ($s) => array_sum(array_column($s, 'count'));
        $this->assertSame('day', $d['range']['unit']);
        $this->assertSame(User::count(), $sum($d['signups']));
        $this->assertSame(Organization::count(), $sum($d['organizations']));
        $mods = collect($d['loan_files_by_module'])->mapWithKeys(fn ($m) => [$m['module'] => $sum($m['series'])])->all();
        $this->assertEquals(['bank' => 1, 'appraisal' => 1], array_intersect_key($mods, ['bank' => 1, 'appraisal' => 1]));
        $this->assertSame(1, $sum($d['inquiries']));
        $this->assertSame(['Starter' => 1, 'Team' => 0, 'Enterprise' => 0], collect($d['plan_distribution'])->pluck('count', 'label')->all());
        $old = $this->getJson($this->url('analytics?from=2001-01-01&to=2001-03-01'))->assertOk()->json();
        $this->assertSame(0, $sum($old['signups']));
        $this->getJson($this->url('analytics?from=2001-01-01&to=2010-01-01'))->assertUnprocessable();
    }

    public function test_signup_approval_queue_blocks_until_approved_and_emails_without_breaking_on_mail_failure(): void
    {
        config(['platform.signup_approval' => true]);
        $this->postJson('/api/auth/register', ['name' => 'New Fictional Owner', 'organization_name' => 'Pending Fictional Co', 'email' => 'pending@example.test', 'password' => 'DevelopmentOnly123', 'password_confirmation' => 'DevelopmentOnly123'])->assertCreated();
        $org = Organization::where('name', 'Pending Fictional Co')->firstOrFail();
        $this->assertSame('pending', $org->approval_status);
        $req = PlatformRequest::where('organization_id', $org->id)->where('type', 'signup')->firstOrFail();
        $newOwner = User::where('email', 'pending@example.test')->first();
        $newOwner->forceFill(['email_verified_at' => now()])->save();
        $this->app['auth']->forgetGuards();
        $this->actingAs($newOwner)->getJson('/api/organizations/'.$org->id)->assertStatus(423)->assertJsonPath('code', 'organization_pending');
        $admin = $this->admin();
        $this->actingAs($admin)->getJson($this->url('approvals'))->assertOk()->assertJsonPath('requests.data.0.id', $req->id);
        $this->postJson($this->url('approvals/'.$req->id.'/decision'), ['decision' => 'reject'])->assertUnprocessable();
        config(['mail.default' => 'missing-mailer-for-test']);
        $this->postJson($this->url('approvals/'.$req->id.'/decision'), ['decision' => 'approve'])->assertOk()->assertJsonPath('request.status', 'approved');
        $this->postJson($this->url('approvals/'.$req->id.'/decision'), ['decision' => 'reject', 'reason' => 'late'])->assertStatus(409);
        $this->assertSame('approved', $org->fresh()->approval_status);
        $this->assertTrue(AuditEvent::where('action', 'admin.request.approved')->where('actor_id', $admin->id)->where('organization_id', $org->id)->exists());
        $this->actingAs($newOwner)->getJson('/api/organizations/'.$org->id)->assertOk();
        config(['platform.signup_approval' => false, 'mail.default' => 'array']);
        $this->app['auth']->guard('web')->logout();
        $this->postJson('/api/auth/register', ['name' => 'Open Owner', 'organization_name' => 'Open Fictional Co', 'email' => 'open@example.test', 'password' => 'DevelopmentOnly123', 'password_confirmation' => 'DevelopmentOnly123'])->assertCreated();
        $this->assertSame('approved', Organization::where('name', 'Open Fictional Co')->first()->approval_status);
        $this->assertSame(1, PlatformRequest::where('type', 'signup')->count());
    }

    public function test_plan_change_requests_flow_from_customer_to_owner_console(): void
    {
        Queue::fake();
        $team = Plan::where('name', 'Team')->first();
        $this->actingAs($this->member('admin'))->postJson('/api/organizations/'.$this->org->id.'/plan-requests', ['plan_id' => $team->id])->assertForbidden();
        $this->actingAs($this->member('broker'))->postJson('/api/organizations/'.$this->org->id.'/plan-requests', ['plan_id' => $team->id])->assertForbidden();
        $outsider = $this->member('owner', Organization::create(['name' => 'Other Fictional Org', 'plan_id' => $team->id]));
        $this->actingAs($outsider)->postJson('/api/organizations/'.$this->org->id.'/plan-requests', ['plan_id' => $team->id])->assertForbidden();
        $this->actingAs($this->owner)->postJson('/api/organizations/'.$this->org->id.'/plan-requests', ['plan_id' => $this->org->plan_id])->assertUnprocessable();
        $this->postJson('/api/organizations/'.$this->org->id.'/plan-requests', ['plan_id' => $team->id, 'message' => 'We are growing.'])->assertCreated();
        $this->postJson('/api/organizations/'.$this->org->id.'/plan-requests', ['plan_id' => $team->id])->assertUnprocessable();
        $this->getJson('/api/organizations/'.$this->org->id.'/plan-requests')->assertOk()->assertJsonPath('requests.0.status', 'pending');
        $this->assertSame('Starter', $this->org->fresh()->plan->name);
        $req = PlatformRequest::where('type', 'plan_change')->firstOrFail();
        $admin = $this->admin();
        $this->actingAs($admin)->postJson($this->url('approvals/'.$req->id.'/decision'), ['decision' => 'approve'])->assertOk();
        $this->assertSame($team->id, $this->org->fresh()->plan_id);
        Queue::assertPushed(SendPlatformEmail::class, fn ($j) => $j->to === 'owner@example.test' && str_contains($j->body, 'approved'));
        $this->assertTrue(AuditEvent::where('action', 'plan_change.requested')->where('actor_id', $this->owner->id)->exists());
        $this->assertEquals(['from' => Plan::where('name', 'Starter')->first()->id, 'to' => $team->id], AuditEvent::where('action', 'admin.request.approved')->first()->metadata['plan']);
    }

    public function test_inquiry_can_be_converted_to_an_owner_invitation_once(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $team = Plan::where('name', 'Team')->first();
        $this->actingAs($admin)->postJson($this->url('inquiries/{inquiry}/convert'), ['organization_name' => 'Example Lending Co', 'plan_id' => $team->id, 'trial_days' => 14])->assertCreated()->assertJsonPath('inquiry.status', 'in_progress');
        $org = Organization::where('name', 'Example Lending Co')->firstOrFail();
        $this->assertSame('trial', $org->subscription_status);
        $inv = Invitation::where('organization_id', $org->id)->firstOrFail();
        $this->assertSame(['prospect@example.test', 'owner'], [$inv->email, $inv->role]);
        Queue::assertPushed(SendPlatformEmail::class, fn ($j) => $j->to === 'prospect@example.test' && str_contains($j->body, '/invitations/accept?token='));
        $this->postJson($this->url('inquiries/{inquiry}/convert'), ['organization_name' => 'Again', 'plan_id' => $team->id])->assertStatus(409);
        $spam = WebsiteInquiry::create(['name' => 'Spam', 'email' => 'spam@example.test', 'company' => 'Spam', 'inquiry_type' => 'demo', 'message' => 'x', 'is_spam' => true]);
        $this->postJson('/api/admin/inquiries/'.$spam->id.'/convert', ['organization_name' => 'Spam', 'plan_id' => $team->id])->assertUnprocessable();
        $this->assertTrue(AuditEvent::where('action', 'website_inquiry.converted')->where('actor_id', $admin->id)->exists());
        // The invited prospect accepts through the existing flow and becomes owner.
        $prospect = User::factory()->create(['email' => 'prospect@example.test']);
        $token = null;
        Queue::assertPushed(SendPlatformEmail::class, function ($j) use (&$token) {
            preg_match('/token=([A-Za-z0-9]+)/', $j->body, $m);
            $token = $m[1] ?? null;

            return true;
        });
        $this->actingAs($prospect)->postJson('/api/invitations/accept', ['token' => $token])->assertSuccessful();
        $this->assertSame('owner', Membership::where('organization_id', $org->id)->where('user_id', $prospect->id)->value('role'));
    }

    private function startSupport(User $admin, ?User $target = null): void
    {
        $this->actingAs($admin)->postJson($this->url('impersonation'), ['user_id' => ($target ?? $this->owner)->id, 'reason' => 'Customer asked for help with a stuck upload (fictional ticket)'])->assertOk()->assertJsonPath('redirect', '/dashboard');
        $this->nextRequest();
    }

    public function test_support_session_safeguards_reason_2fa_targets_and_kill_switch(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson($this->url('impersonation'), ['user_id' => $this->owner->id])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson($this->url('impersonation'), ['user_id' => $this->owner->id, 'reason' => 'short'])->assertUnprocessable();
        $this->postJson($this->url('impersonation'), ['user_id' => $this->admin()->id, 'reason' => 'Trying to view another admin'])->assertUnprocessable();
        $this->postJson($this->url('impersonation'), ['user_id' => $admin->id, 'reason' => 'Trying to view myself here'])->assertUnprocessable();
        $disabled = User::factory()->create(['disabled_at' => now()]);
        $this->postJson($this->url('impersonation'), ['user_id' => $disabled->id, 'reason' => 'Trying to view a disabled user'])->assertUnprocessable();
        config(['platform.console_require_2fa' => false]);
        $no2fa = $this->admin(false);
        $this->actingAs($no2fa)->postJson($this->url('impersonation'), ['user_id' => $this->owner->id, 'reason' => 'Admin without two-factor sign-in'])->assertForbidden();
        config(['platform.impersonation.enabled' => false]);
        $this->actingAs($admin)->postJson($this->url('impersonation'), ['user_id' => $this->owner->id, 'reason' => 'Kill switch should stop this'])->assertForbidden();
        $this->assertSame(0, ImpersonationSession::count());
        $this->assertSame($admin->id, auth()->id());
    }

    public function test_support_session_switches_identity_blocks_sensitive_actions_and_audits_both_ids(): void
    {
        $admin = $this->admin();
        $r = $this->actingAs($this->owner)->post('/api/files/'.$this->file->id.'/documents', ['file' => UploadedFile::fake()->createWithContent('fictional.pdf', SampleData::pdf()), 'fictional_sample' => true], ['Accept' => 'application/json'])->assertCreated();
        $this->nextRequest();
        $this->startSupport($admin);
        $this->assertSame($this->owner->id, auth()->id());
        $me = $this->getJson('/api/me')->assertOk()->assertJsonPath('user.id', $this->owner->id)->assertJsonPath('impersonation.admin_name', 'Fictional Platform Admin');
        $this->assertStringNotContainsString('admin_id', $me->getContent());
        $started = AuditEvent::where('action', 'impersonation.started')->firstOrFail();
        $this->assertSame($admin->id, $started->actor_id);
        $this->assertSame([$admin->id, $this->owner->id], [$started->metadata['admin_id'], $started->metadata['target_user_id']]);
        $this->assertTrue(AuditEvent::where('action', 'platform_support.accessed')->where('organization_id', $this->org->id)->exists());
        $this->getJson('/api/organizations/'.$this->org->id.'/audit')->assertOk()->assertJsonFragment(['action' => 'platform_support.accessed']);

        $blocked = [
            ['put', '/api/auth/user/password', ['current_password' => 'x', 'password' => 'NewPassword-123456', 'password_confirmation' => 'NewPassword-123456']],
            ['put', '/api/auth/user/profile-information', ['name' => 'Changed', 'email' => 'changed@example.test']],
            ['post', '/api/auth/user/two-factor-authentication', []],
            ['delete', '/api/auth/user/two-factor-authentication', []],
            ['get', '/api/auth/user/two-factor-recovery-codes', []],
            ['post', '/api/organizations/'.$this->org->id.'/billing', ['action' => 'upgrade', 'plan_id' => 2, 'idempotency_key' => 'k1']],
            ['post', '/api/organizations/'.$this->org->id.'/invitations', ['email' => 'new@example.test', 'role' => 'admin']],
            ['post', '/api/organizations/'.$this->org->id.'/plan-requests', ['plan_id' => 2]],
            ['post', '/api/files/'.$this->file->id.'/appraisal/guidelines', []],
            ['get', '/api/document-versions/'.$r->json('version.id').'/content', []],
            ['get', '/api/files/'.$this->file->id.'/appraisal/runs/1/artifacts/1', []],
            ['get', '/api/admin/overview', []],
            ['post', '/api/admin/impersonation', ['user_id' => $this->owner->id, 'reason' => 'nested session attempt']],
        ];
        foreach ($blocked as [$m, $u, $b]) {
            $this->json($m, $u, $b)->assertForbidden()->assertJsonPath('code', 'impersonation_blocked');
        }
        $this->get('/admin')->assertForbidden()->assertSee('Support session in progress');
        $this->assertSame('Fictional Owner', $this->owner->fresh()->name);
        $this->assertSame(0, Invitation::count());

        $this->postJson('/api/files/'.$this->file->id.'/notes', ['body' => 'Support checked the upload queue.', 'visibility' => 'shared'])->assertSuccessful();
        $write = AuditEvent::where('action', 'impersonation.write')->latest('id')->firstOrFail();
        $this->assertSame($this->owner->id, $write->actor_id);
        $this->assertSame([$admin->id, $this->owner->id], [$write->metadata['admin_id'], $write->metadata['target_user_id']]);
        $note = AuditEvent::where('action', 'note.added')->latest('id')->firstOrFail();
        $this->assertSame([$admin->id, $this->owner->id], [$note->metadata['impersonator_id'], $note->metadata['impersonated_user_id']]);

        $this->postJson('/api/impersonation/stop')->assertOk()->assertJsonPath('redirect', '/admin');
        $this->nextRequest();
        $this->assertSame($admin->id, auth()->id());
        $this->getJson($this->url('me'))->assertOk()->assertJsonPath('user.two_factor', true);
        $this->getJson($this->url('overview'))->assertOk();
        $this->getJson('/api/me')->assertJsonPath('impersonation', null);
        $this->assertSame('returned', ImpersonationSession::first()->end_reason);
        $this->assertTrue(AuditEvent::where('action', 'impersonation.ended')->where('actor_id', $admin->id)->where('metadata->target_user_id', $this->owner->id)->exists());
        $this->postJson('/api/impersonation/stop')->assertStatus(409);
    }

    public function test_document_download_in_support_session_only_when_explicitly_allowed(): void
    {
        config(['platform.impersonation.allow_document_download' => true]);
        $r = $this->actingAs($this->owner)->post('/api/files/'.$this->file->id.'/documents', ['file' => UploadedFile::fake()->createWithContent('fictional.pdf', SampleData::pdf()), 'fictional_sample' => true], ['Accept' => 'application/json'])->assertCreated();
        $this->nextRequest();
        $this->startSupport($this->admin());
        $res = $this->get('/api/document-versions/'.$r->json('version.id').'/content');
        $this->assertNotSame(403, $res->getStatusCode());
    }

    public function test_support_session_expires_and_restores_the_admin(): void
    {
        $admin = $this->admin();
        $this->startSupport($admin);
        $this->getJson('/api/me')->assertJsonPath('user.id', $this->owner->id);
        $this->travel(31)->minutes();
        $this->getJson('/api/files')->assertUnauthorized()->assertJsonPath('code', 'impersonation_ended')->assertJsonPath('redirect', '/admin');
        $this->nextRequest();
        $this->assertSame($admin->id, auth()->id());
        $this->assertSame('expired', ImpersonationSession::first()->end_reason);
        $this->getJson('/api/me')->assertJsonPath('user.id', $admin->id)->assertJsonPath('impersonation', null);
    }

    public function test_logout_during_support_session_ends_it(): void
    {
        $this->startSupport($this->admin());
        $this->postJson('/api/auth/logout')->assertSuccessful();
        $this->assertSame('logout', ImpersonationSession::first()->end_reason);
        $this->assertTrue(AuditEvent::where('action', 'impersonation.ended')->where('metadata->end_reason', 'logout')->exists());
    }
}
