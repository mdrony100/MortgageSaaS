<?php

namespace Tests\Feature;

use App\Billing\PageCredits;
use App\Jobs\SendPlatformEmail;
use App\Models\Bank\Run;
use App\Models\Borrower;
use App\Models\DemoRequest;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PageCreditAccount;
use App\Models\PageCreditEntry;
use App\Models\PaymentRequest;
use App\Models\Plan;
use App\Models\User;
use App\Services\SampleData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PageCreditsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::factory()->create(['name' => 'Fictional Owner']);
        $this->admin = User::factory()->create(['name' => 'Fictional Org Admin']);
        $this->org = Organization::create(['name' => 'Fictional Lending', 'plan_id' => Plan::where('name', 'Starter')->first()->id]);
        Membership::create(['organization_id' => $this->org->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'org_wide_access' => true]);
        Membership::create(['organization_id' => $this->org->id, 'user_id' => $this->admin->id, 'role' => 'admin', 'org_wide_access' => true]);
    }

    public function test_ledger_math_grants_consumes_once_and_rejects_a_negative_balance(): void
    {
        $this->assertSame(80, PageCredits::percentUsed(80, 20));
        $this->assertSame(79, PageCredits::percentUsed(79, 21));
        $this->assertTrue(PageCredits::warns(80, 20));
        $this->assertFalse(PageCredits::warns(79, 21));
        $this->assertTrue(PageCredits::warns(10, 0));

        $account = PageCreditAccount::where('organization_id', $this->org->id)->firstOrFail();
        $this->assertSame(500, $account->balance);
        $this->assertSame(500, (int) PageCreditEntry::where('organization_id', $this->org->id)->where('type', 'monthly_allocation')->value('pages'));

        $credits = app(PageCredits::class);
        $credits->consume($this->org->id, 30, 'test-run:1', ['reason' => 'Bank statement pages processed', 'actor_id' => $this->owner->id, 'loan_file_id' => null, 'pipeline' => 'bank']);
        $credits->consume($this->org->id, 30, 'test-run:1', ['reason' => 'Bank statement pages processed', 'actor_id' => $this->owner->id, 'pipeline' => 'bank']);
        $account->refresh();
        $this->assertSame(470, $account->balance);
        $this->assertSame(30, $account->used_this_period);
        $this->assertSame(1, PageCreditEntry::where('organization_id', $this->org->id)->where('type', 'consumption')->count());

        $credits->adjust($this->org, 20, 'Courtesy pages', $this->owner->id);
        $this->assertSame(490, $account->fresh()->balance);
        $this->assertSame('adjustment', PageCreditEntry::where('organization_id', $this->org->id)->where('type', 'adjustment')->value('type'));

        try {
            $credits->adjust($this->org, -10000, 'Too many', $this->owner->id);
            $this->fail('A debit below zero should be refused.');
        } catch (ValidationException $e) {
            $this->assertSame('That adjustment would take the balance below zero.', $e->errors()['pages'][0]);
        }
        $this->assertSame(490, $account->fresh()->balance);
    }

    public function test_period_roll_adds_the_monthly_quota_and_resets_usage(): void
    {
        $account = PageCreditAccount::where('organization_id', $this->org->id)->firstOrFail();
        $account->update(['balance' => 470, 'used_this_period' => 30, 'period_ends_at' => now()->subDay()]);
        $summary = app(PageCredits::class)->summary($this->org->fresh());
        $this->assertSame(970, $summary['balance']);
        $this->assertSame(0, $summary['used_this_period']);
        $this->assertSame(2, PageCreditEntry::where('organization_id', $this->org->id)->where('type', 'monthly_allocation')->count());
    }

    public function test_processing_is_blocked_with_a_customer_message_when_pages_run_out(): void
    {
        Storage::fake('private');
        $borrower = Borrower::create(['organization_id' => $this->org->id, 'name' => 'Fictional Borrower']);
        $file = LoanFile::create(['organization_id' => $this->org->id, 'borrower_id' => $borrower->id, 'created_by' => $this->owner->id, 'name' => 'Fictional Review', 'module' => 'bank', 'is_demo' => true]);
        PageCreditAccount::where('organization_id', $this->org->id)->update(['balance' => 0, 'used_this_period' => 500]);

        $this->actingAs($this->owner);
        $uploaded = $this->post('/api/files/'.$file->id.'/documents', [
            'file' => UploadedFile::fake()->createWithContent('FICTIONAL.pdf', SampleData::pdf()),
            'fictional_sample' => true,
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->postJson('/api/files/'.$file->id.'/bank/runs', [
            'idempotency_key' => 'no-pages',
            'document_version_ids' => [$uploaded->json('version.id')],
            'workflow' => 'personal',
            'months' => 12,
            'end_month' => '2025-12',
        ])->assertUnprocessable()->assertJsonPath('errors.pages.0', PageCredits::INSUFFICIENT);

        $this->assertSame(0, Run::count());
        $this->assertSame(0, PageCreditAccount::where('organization_id', $this->org->id)->value('balance'));
    }

    public function test_a_per_file_page_cap_blocks_before_processing(): void
    {
        Storage::fake('private');
        Plan::where('name', 'Starter')->update(['per_file_page_cap' => 0]);
        $borrower = Borrower::create(['organization_id' => $this->org->id, 'name' => 'Fictional Borrower']);
        $file = LoanFile::create(['organization_id' => $this->org->id, 'borrower_id' => $borrower->id, 'created_by' => $this->owner->id, 'name' => 'Fictional Review', 'module' => 'bank', 'is_demo' => true]);
        $this->actingAs($this->owner);
        $uploaded = $this->post('/api/files/'.$file->id.'/documents', [
            'file' => UploadedFile::fake()->createWithContent('FICTIONAL.pdf', SampleData::pdf()),
            'fictional_sample' => true,
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/api/files/'.$file->id.'/bank/runs', [
            'idempotency_key' => 'over-cap',
            'document_version_ids' => [$uploaded->json('version.id')],
            'workflow' => 'personal',
            'months' => 12,
            'end_month' => '2025-12',
        ])->assertUnprocessable()->assertJsonPath('errors.pages.0', PageCredits::FILE_CAP);
        $this->assertSame(500, PageCreditAccount::where('organization_id', $this->org->id)->value('balance'));
    }

    public function test_warning_starts_at_eighty_percent_and_admins_can_request_pages(): void
    {
        PageCreditAccount::where('organization_id', $this->org->id)->update(['balance' => 100, 'used_this_period' => 400]);
        $this->actingAs($this->owner)->getJson('/api/organizations/'.$this->org->id.'/page-credits')
            ->assertOk()
            ->assertJsonPath('warning', true)
            ->assertJsonPath('percent_used', 80)
            ->assertJsonPath('plan.name', 'Starter')
            ->assertJsonPath('can_request', true);

        PageCreditAccount::where('organization_id', $this->org->id)->update(['balance' => 21, 'used_this_period' => 79]);
        $this->getJson('/api/organizations/'.$this->org->id.'/page-credits')->assertJsonPath('warning', false);

        $broker = User::factory()->create();
        Membership::create(['organization_id' => $this->org->id, 'user_id' => $broker->id, 'role' => 'broker', 'org_wide_access' => false]);
        $pro = Plan::where('name', 'Pro')->first();
        $this->actingAs($broker)->postJson('/api/organizations/'.$this->org->id.'/payment-requests', ['kind' => 'plan', 'plan_id' => $pro->id])->assertForbidden();
        $this->actingAs($broker)->getJson('/api/organizations/'.$this->org->id.'/page-credits')->assertOk()->assertJsonPath('can_request', false)->assertJsonPath('requests', []);

        $this->actingAs($this->admin)->postJson('/api/organizations/'.$this->org->id.'/payment-requests', ['kind' => 'plan', 'plan_id' => $pro->id, 'note' => 'We need more pages.'])
            ->assertCreated()
            ->assertJsonPath('request.status', 'pending')
            ->assertJsonPath('request.amount', '199.00');
        $this->postJson('/api/organizations/'.$this->org->id.'/payment-requests', ['kind' => 'plan', 'plan_id' => $pro->id])->assertUnprocessable();
        $this->assertSame('Starter', $this->org->fresh()->plan->name);
    }

    public function test_owner_admin_approval_applies_the_plan_or_pages_and_rejection_does_not(): void
    {
        Bus::fake();
        $staff = User::factory()->create(['email' => 'staff@example.test']);
        $staff->forceFill(['platform_admin' => true])->save();
        $pro = Plan::where('name', 'Pro')->firstOrFail();

        $this->actingAs($this->admin)->postJson('/api/organizations/'.$this->org->id.'/payment-requests', ['kind' => 'plan', 'plan_id' => $pro->id, 'note' => 'Moving up.'])->assertCreated();
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === 'staff@example.test' && str_contains($job->subject, 'new page or plan request'));

        $id = PaymentRequest::first()->id;
        $this->actingAs($this->owner)->postJson('/api/admin/payment-requests/'.$id.'/approve', ['payment_method' => 'Wire', 'payment_reference' => 'WIRE-100'])->assertForbidden();
        $this->actingAs($staff)->postJson('/api/admin/payment-requests/'.$id.'/approve', [])->assertUnprocessable();
        $this->actingAs($staff)->postJson('/api/admin/payment-requests/'.$id.'/approve', [
            'payment_method' => 'Wire',
            'payment_reference' => 'WIRE-100',
            'payment_note' => 'Received Tuesday.',
        ])->assertOk()->assertJsonPath('request.status', 'approved');

        $this->assertSame('Pro', $this->org->fresh()->plan->name);
        $this->assertNotNull(PaymentRequest::find($id)->paid_offline_at);
        $this->assertSame('Wire', PaymentRequest::find($id)->payment_method);
        $this->assertSame(2000, PageCreditAccount::where('organization_id', $this->org->id)->value('balance'));
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === $this->admin->email && str_contains($job->body, 'approved'));

        $this->actingAs($this->admin)->postJson('/api/organizations/'.$this->org->id.'/payment-requests', ['kind' => 'pack', 'pack_pages' => 500])->assertCreated();
        $pack = PaymentRequest::where('kind', 'pack')->firstOrFail();
        $this->assertSame('79.00', $pack->amount);
        $this->actingAs($staff)->postJson('/api/admin/payment-requests/'.$pack->id.'/reject', ['reason' => 'Need a purchase order first.'])->assertOk();
        $this->assertSame('rejected', $pack->fresh()->status);
        $this->assertSame(2000, PageCreditAccount::where('organization_id', $this->org->id)->value('balance'));
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === $this->admin->email && str_contains($job->body, 'Need a purchase order'));

        $this->actingAs($this->admin)->postJson('/api/organizations/'.$this->org->id.'/payment-requests', ['kind' => 'pack', 'pack_pages' => 500])->assertCreated();
        $again = PaymentRequest::where('kind', 'pack')->where('status', 'pending')->firstOrFail();
        $before = PageCreditAccount::where('organization_id', $this->org->id)->value('balance');
        $this->actingAs($staff)->postJson('/api/admin/payment-requests/'.$again->id.'/approve', ['payment_method' => 'Check', 'payment_reference' => 'CHK-9'])->assertOk();
        $this->assertSame($before + 500, PageCreditAccount::where('organization_id', $this->org->id)->value('balance'));
        $this->assertSame('top_up', PageCreditEntry::where('organization_id', $this->org->id)->where('type', 'top_up')->value('type'));

        $this->actingAs($staff)->postJson('/api/admin/organizations/'.$this->org->id.'/credits', ['pages' => -10, 'reason' => 'Correct a duplicate count'])->assertOk();
        $this->assertSame($before + 490, PageCreditAccount::where('organization_id', $this->org->id)->value('balance'));
    }

    public function test_demo_form_stores_requests_blocks_spam_and_is_rate_limited(): void
    {
        Bus::fake();
        config(['website.inquiry_notify_email' => 'sales@example.test']);
        $this->get('/request-demo')->assertOk()->assertSee('Request a demo')->assertSee('Monthly loan volume or pages')->assertSee('name="company_website"', false);

        $payload = ['name' => 'Fictional Prospect', 'company' => 'Example Lending Co', 'email' => 'Prospect@Example.test', 'phone' => '555-0100', 'monthly_volume' => '40 loans', 'message' => 'Please show us the bank statement workflow.', 'company_website' => ''];
        $this->post('/request-demo', $payload)->assertRedirect('/request-demo')->assertSessionHas('demo_saved');
        $saved = DemoRequest::firstOrFail();
        $this->assertSame('prospect@example.test', $saved->email);
        $this->assertSame('new', $saved->status);
        $this->assertFalse($saved->is_spam);
        Bus::assertDispatched(SendPlatformEmail::class, fn ($job) => $job->to === 'sales@example.test');

        $this->post('/request-demo', array_replace($payload, ['email' => 'spam@example.test', 'company_website' => 'http://spam.example']))->assertSessionHas('demo_saved');
        $this->assertTrue(DemoRequest::where('email', 'spam@example.test')->first()->is_spam);
        $this->assertSame('skipped_spam', DemoRequest::where('email', 'spam@example.test')->value('notification_status'));

        $staff = User::factory()->create();
        $staff->forceFill(['platform_admin' => true])->save();
        $this->actingAs($this->owner)->getJson('/api/admin/demo-requests')->assertForbidden();
        $this->actingAs($staff)->getJson('/api/admin/demo-requests?spam=all')->assertOk()->assertJsonFragment(['email' => 'spam@example.test']);
        $this->actingAs($staff)->getJson('/api/admin/demo-requests?spam=exclude')->assertOk();
        $visible = collect($this->getJson('/api/admin/demo-requests?spam=exclude')->json('requests.data'))->pluck('email');
        $this->assertTrue($visible->contains('prospect@example.test'));
        $this->assertFalse($visible->contains('spam@example.test'));
        $id = DemoRequest::where('email', 'prospect@example.test')->value('id');
        $this->patchJson('/api/admin/demo-requests/'.$id, ['status' => 'contacted'])->assertOk()->assertJsonPath('request.status', 'contacted');
        $this->postJson('/api/admin/demo-requests/'.$id.'/notes', ['body' => 'Called the office.'])->assertCreated()->assertJsonPath('request.notes.0.body', 'Called the office.');
        $this->patchJson('/api/admin/demo-requests/'.$id, ['status' => 'archived'])->assertUnprocessable();
    }

    public function test_demo_form_is_rate_limited(): void
    {
        config(['website.demo_rate_limit.per_minute' => 2, 'website.inquiry_notify_email' => 'sales@example.test']);
        $payload = ['name' => 'Fictional Prospect', 'company' => 'Example Lending Co', 'email' => 'a@example.test', 'phone' => '555-0100', 'monthly_volume' => '40 loans', 'message' => 'Please show us the bank statement workflow.', 'company_website' => ''];
        $this->from('/request-demo')->post('/request-demo', $payload)->assertSessionHas('demo_saved');
        $this->from('/request-demo')->post('/request-demo', array_replace($payload, ['email' => 'b@example.test']))->assertSessionHas('demo_saved');
        $this->from('/request-demo')->post('/request-demo', array_replace($payload, ['email' => 'c@example.test']))->assertRedirect('/request-demo')->assertSessionHasErrors('form');
        $this->assertSame(2, DemoRequest::count());
    }
}
