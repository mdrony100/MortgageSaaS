<?php

namespace Tests\Feature;

use App\Billing\PageCredits;
use App\Jobs\SendAuthNotification;
use App\Jobs\SendPlatformEmail;
use App\Mail\BrandedMessage;
use App\Models\Borrower;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PageCreditAccount;
use App\Models\Plan;
use App\Models\User;
use App\Services\NotificationMail;
use App\Services\SupportDesk;
use App\Website\DemoCaptcha;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_shared_layout_is_used_for_platform_verification_reset_and_markdown_mail(): void
    {
        Mail::fake();
        $job = new SendPlatformEmail('owner@example.test', 'Non-QM Doc AI: workspace invitation', "You are invited to a workspace.\n\nhttps://nonqmdoc.ai/invitations/accept?token=abc");
        $job->handle();
        Mail::assertSent(BrandedMessage::class, function (BrandedMessage $mail) {
            $html = $mail->render();
            $text = view('mail.branded-text', $mail->viewData())->render();

            return $mail->hasTo('owner@example.test')
                && str_contains($html, 'https://nonqmdoc.ai/images/brand/non-qm-doc-logo.png')
                && str_contains($html, 'alt="Non-QM Doc AI"')
                && str_contains($html, 'info@nonqmdoc.ai')
                && str_contains($html, 'Accept invitation')
                && str_contains($text, 'Accept invitation')
                && str_contains($text, 'info@nonqmdoc.ai');
        });

        $user = User::factory()->create(['email' => 'person@example.test']);
        $verify = (new VerifyEmail)->toMail($user);
        $verifyHtml = (string) $verify->render();
        $this->assertStringContainsString('Confirm your email', $verifyHtml);
        $this->assertStringContainsString('alt="Non-QM Doc AI"', $verifyHtml);
        $this->assertStringContainsString('https://nonqmdoc.ai/images/brand/non-qm-doc-logo.png', $verifyHtml);
        $this->assertStringContainsString('/email/verify/', $verifyHtml);

        $reset = (new ResetPassword('reset-token'))->toMail($user);
        $resetHtml = (string) $reset->render();
        $this->assertStringContainsString('Choose a new password', $resetHtml);
        $this->assertStringContainsString('reset-token', $resetHtml);
        $this->assertStringContainsString('info@nonqmdoc.ai', $resetHtml);

        $markdown = (string) (new MailMessage)->subject('Hello')->line('A markdown notice.')->action('Open', 'https://nonqmdoc.ai/dashboard')->render();
        $this->assertStringContainsString('https://nonqmdoc.ai/images/brand/non-qm-doc-logo.png', $markdown);
        $this->assertStringContainsString('alt="Non-QM Doc AI"', $markdown);
        $this->assertStringContainsString('info@nonqmdoc.ai', $markdown);
        $this->assertStringContainsString('#22c6e6', $markdown);
    }

    public function test_signup_queues_welcome_admin_notice_and_verification(): void
    {
        Bus::fake([SendPlatformEmail::class, SendAuthNotification::class]);
        config(['website.inquiry_notify_email' => 'ops@example.test']);
        $this->postJson('/api/auth/register', [
            'name' => 'Sample Owner',
            'organization_name' => 'Sample Workspace',
            'email' => 'new@example.test',
            'password' => 'DevelopmentOnly123',
            'password_confirmation' => 'DevelopmentOnly123',
        ])->assertCreated();

        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === 'new@example.test' && str_contains($job->subject, 'Welcome') && $job->group === 'customer_account');
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === 'ops@example.test' && str_contains($job->body, 'Sample Workspace') && $job->group === 'admin_signups');
        Bus::assertDispatched(SendAuthNotification::class, fn ($job) => $job->kind === 'verify');
    }

    public function test_admin_signup_toggle_skips_the_admin_email_only(): void
    {
        Bus::fake([SendPlatformEmail::class]);
        config(['notifications.groups.admin_signups' => false, 'website.inquiry_notify_email' => 'ops@example.test']);
        $this->postJson('/api/auth/register', [
            'name' => 'Sample Owner',
            'organization_name' => 'Quiet Workspace',
            'email' => 'quiet@example.test',
            'password' => 'DevelopmentOnly123',
            'password_confirmation' => 'DevelopmentOnly123',
        ])->assertCreated();

        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === 'quiet@example.test');
        Bus::assertNotDispatched(SendPlatformEmail::class, fn ($job) => $job->to === 'ops@example.test');
    }

    public function test_demo_request_confirms_to_the_requester_and_notifies_admin(): void
    {
        Bus::fake([SendPlatformEmail::class]);
        config(['website.inquiry_notify_email' => 'sales@example.test', 'website.demo_rate_limit.per_minute' => 30]);
        $this->get('/request-demo');
        $question = session(DemoCaptcha::KEY);
        $this->post('/request-demo', [
            'name' => 'Fictional Prospect',
            'company' => 'Example Lending Co',
            'email' => 'prospect@example.test',
            'phone_country' => 'US',
            'phone' => '201 555 0123',
            'monthly_volume' => '40 loans',
            'captcha' => (string) ((int) $question['a'] + (int) $question['b']),
        ])->assertRedirect('/request-demo');

        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === 'sales@example.test' && $job->group === 'admin_website');
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === 'prospect@example.test' && str_contains($job->body, 'demo request') && $job->group === 'customer_account');
    }

    public function test_payment_request_confirms_to_the_customer(): void
    {
        Bus::fake([SendPlatformEmail::class]);
        [$owner, $org] = $this->workspace();
        $pro = Plan::where('name', 'Pro')->firstOrFail();
        $this->actingAs($owner)->postJson('/api/organizations/'.$org->id.'/payment-requests', [
            'kind' => 'plan',
            'plan_id' => $pro->id,
            'note' => 'Need more pages.',
        ])->assertCreated();

        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === $owner->email && $job->group === 'customer_billing' && str_contains($job->body, 'Nothing has been charged'));
    }

    public function test_file_share_and_repeat_share_email_once(): void
    {
        Bus::fake([SendPlatformEmail::class]);
        [$owner, $org, $file] = $this->workspace(true);
        $member = User::factory()->create(['email' => 'member@example.test']);
        Membership::create(['organization_id' => $org->id, 'user_id' => $member->id, 'role' => 'processor', 'org_wide_access' => false]);
        $this->actingAs($owner)->postJson('/api/files/'.$file->id.'/shares', ['user_id' => $member->id])->assertSuccessful();
        $this->postJson('/api/files/'.$file->id.'/shares', ['user_id' => $member->id])->assertSuccessful();

        Bus::assertDispatched(SendPlatformEmail::class, 1);
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === 'member@example.test' && str_contains($job->body, '/files/'.$file->id) && $job->group === 'customer_team');
    }

    public function test_support_mail_is_branded_and_a_send_failure_still_opens_the_ticket(): void
    {
        Mail::fake();
        config(['website.inquiry_notify_email' => 'ops@example.test']);
        SupportDesk::notify(['ops@example.test'], 'Support ticket opened: Cannot open a statement', "A support ticket was opened.\n\n".url('/admin/support-tickets'), 'admin_support');
        Mail::assertSent(BrandedMessage::class, function (BrandedMessage $mail) {
            return $mail->hasTo('ops@example.test')
                && str_contains($mail->render(), 'alt="Non-QM Doc AI"')
                && str_contains($mail->render(), '/admin/support-tickets');
        });

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));
        [$owner, $org] = $this->workspace();
        $this->actingAs($owner)->postJson('/api/organizations/'.$org->id.'/support-tickets', [
            'subject' => 'Cannot open a statement',
            'category' => 'documents',
            'priority' => 'normal',
            'message' => 'The upload stays on the loan file page.',
        ])->assertCreated();
    }

    public function test_low_and_exhausted_credits_email_once_per_period(): void
    {
        Bus::fake([SendPlatformEmail::class]);
        config(['website.inquiry_notify_email' => 'ops@example.test']);
        [$owner, $org] = $this->workspace();
        $account = PageCreditAccount::where('organization_id', $org->id)->firstOrFail();
        $account->update(['balance' => 40, 'used_this_period' => 10]);
        NotificationMail::credits($account->fresh());
        NotificationMail::credits($account->fresh());
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === $owner->email && str_contains($job->subject, 'running low'));
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === 'ops@example.test' && str_contains($job->subject, 'low'));
        $this->assertSame(2, collect(Bus::dispatched(SendPlatformEmail::class))->count());

        Bus::fake([SendPlatformEmail::class]);
        $account->update(['balance' => 0]);
        NotificationMail::credits($account->fresh());
        NotificationMail::credits($account->fresh());
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === $owner->email && str_contains($job->subject, 'used up'));
        $this->assertSame(2, collect(Bus::dispatched(SendPlatformEmail::class))->count());
    }

    public function test_credit_toggle_off_sends_nothing(): void
    {
        Bus::fake([SendPlatformEmail::class]);
        config(['notifications.groups.customer_billing' => false, 'notifications.groups.admin_credits' => false]);
        [$owner, $org] = $this->workspace();
        $account = PageCreditAccount::where('organization_id', $org->id)->firstOrFail();
        $account->update(['balance' => 0, 'used_this_period' => 20]);
        NotificationMail::credits($account->fresh());
        Bus::assertNothingDispatched();
    }

    public function test_trial_and_period_reminders_send_once(): void
    {
        Bus::fake([SendPlatformEmail::class]);
        [$owner, $org] = $this->workspace();
        $org->update(['subscription_status' => 'trial', 'trial_ends_at' => now()->addDays(2)]);
        PageCreditAccount::where('organization_id', $org->id)->update(['period_ends_at' => now()->addDays(2)]);
        $this->artisan('notifications:billing-reminders')->assertOk();
        $this->artisan('notifications:billing-reminders')->assertOk();

        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === $owner->email && str_contains($job->subject, 'trial is ending'));
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === $owner->email && str_contains($job->subject, 'page-credit period'));
        $this->assertSame(2, collect(Bus::dispatched(SendPlatformEmail::class))->count());
    }

    public function test_processing_ready_and_failed_emails_are_sent_once_without_financial_details(): void
    {
        Bus::fake([SendPlatformEmail::class]);
        [$owner, $org, $file] = $this->workspace(true);
        NotificationMail::processing('bank', 'ready', $org->id, $file->id, $owner->id, 'run-1');
        NotificationMail::processing('bank', 'ready', $org->id, $file->id, $owner->id, 'run-1');
        NotificationMail::processing('appraisal', 'failed', $org->id, $file->id, $owner->id, 'run-2');
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === $owner->email && str_contains($job->body, 'bank statement') && str_contains($job->body, '/files/'.$file->id) && ! str_contains($job->body, '330000'));
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === $owner->email && str_contains($job->subject, 'could not finish') && str_contains($job->body, 'appraisal and rental'));
        $this->assertSame(2, collect(Bus::dispatched(SendPlatformEmail::class))->count());

        $owner->forceFill(['email_verified_at' => null])->save();
        NotificationMail::processing('bank', 'ready', $org->id, $file->id, $owner->id, 'run-3');
        $this->assertSame(2, collect(Bus::dispatched(SendPlatformEmail::class))->count());
    }

    public function test_settings_can_turn_a_notification_group_off(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill([
            'platform_admin' => true,
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ])->save();
        config(['platform.console_require_2fa' => true]);
        $this->actingAs($admin)->putJson('/api/admin/settings', ['notify_customer_processing' => false])
            ->assertOk()
            ->assertJsonPath('settings.notify_customer_processing.value', false);
        $this->assertFalse(config('notifications.groups.customer_processing'));
    }

    public function test_page_credit_math_still_warns_below_ten_percent_or_fifty_pages(): void
    {
        $this->assertTrue(NotificationMail::isLow(49, 100));
        $this->assertTrue(NotificationMail::isLow(9, 100));
        $this->assertFalse(NotificationMail::isLow(80, 20));
        $this->assertFalse(NotificationMail::isLow(0, 50));
        $this->assertSame(80, PageCredits::percentUsed(80, 20));
    }

    /** @return array{0: User, 1: Organization, 2?: LoanFile} */
    private function workspace(bool $withFile = false): array
    {
        $owner = User::factory()->create(['email' => 'owner-'.bin2hex(random_bytes(3)).'@example.test']);
        $org = Organization::create(['name' => 'Fictional Lending', 'plan_id' => Plan::where('name', 'Starter')->first()->id]);
        Membership::create(['organization_id' => $org->id, 'user_id' => $owner->id, 'role' => 'owner', 'org_wide_access' => true]);
        if (! $withFile) {
            return [$owner, $org];
        }
        $borrower = Borrower::create(['organization_id' => $org->id, 'name' => 'Fictional Borrower']);
        $file = LoanFile::create([
            'organization_id' => $org->id,
            'borrower_id' => $borrower->id,
            'created_by' => $owner->id,
            'name' => 'Fictional Review',
            'module' => 'bank',
            'is_demo' => true,
        ]);

        return [$owner, $org, $file];
    }
}
