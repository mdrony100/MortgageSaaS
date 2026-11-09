<?php

namespace Tests\Feature;

use App\Jobs\SendWebsiteInquiryNotification;
use App\Mail\WebsiteInquiryReceived;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\WebsiteInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        '/' => 'Turn mortgage documents into clear, reviewable insights.',
        '/features' => 'Supports forms 1004, 1025, 1007 and 216.',
        '/how-it-works' => 'does not issue lender approvals',
        '/pricing' => 'Contact for pricing',
        '/security' => 'Private document access',
        '/faq' => 'Can I connect bank accounts directly?',
        '/contact' => 'Request a demo or ask a question',
        '/privacy' => 'DRAFT — not an approved legal document',
        '/terms' => 'DRAFT — not an approved legal document',
    ];

    private function valid(array $override = []): array
    {
        return array_replace(['name' => 'Fictional Prospect', 'email' => 'Prospect@Example.test', 'company' => 'Example Lending Co', 'role' => 'Processor', 'inquiry_type' => 'demo', 'message' => 'Please show us the bank statement workflow.', 'homepage_url' => ''], $override);
    }

    private function orgUser(string $role): User
    {
        $this->seed();
        $u = User::factory()->create();
        $org = Organization::create(['name' => 'Fictional Org '.$role, 'plan_id' => Plan::first()->id]);
        Membership::create(['organization_id' => $org->id, 'user_id' => $u->id, 'role' => $role, 'org_wide_access' => true]);

        return $u;
    }

    public function test_every_public_page_renders_server_side_with_unique_meta_and_navigation(): void
    {
        $titles = [];
        foreach (self::PAGES as $path => $text) {
            $r = $this->get($path)->assertOk()->assertSee($text, false);
            $html = $r->getContent();
            preg_match('~<title>(.*?)</title>~', $html, $t);
            $titles[] = $t[1];
            $this->assertMatchesRegularExpression('~<meta name="description" content="[^"]{20,}">~', $html);
            $this->assertStringContainsString('<link rel="canonical" href="'.config('website.url').($path === '/' ? '/' : $path).'">', $html);
            $this->assertStringContainsString('property="og:title"', $html);
            $this->assertStringContainsString('name="twitter:card"', $html);
            foreach (['Home', 'Features', 'How It Works', 'Pricing', 'FAQ', 'Contact', 'Sign In', 'Request a Demo', 'Privacy Policy', 'Terms of Service'] as $nav) {
                $this->assertStringContainsString('>'.$nav.'<', $html, "$path nav $nav");
            }
            $this->assertStringContainsString('href="#main"', $html);
            $this->assertStringContainsString('aria-controls="site-mobile-nav"', $html);
            $this->assertStringContainsString('aria-expanded="false"', $html);
            $this->assertStringContainsString('build/assets/site-', $html);
            $this->assertStringNotContainsString('build/assets/app-', $html, 'dashboard bundle must not load on public pages');
            $this->assertStringNotContainsString('Open Dashboard', $html);
        }
        $this->assertCount(count(self::PAGES), array_unique($titles));
    }

    public function test_root_is_homepage_and_dashboard_spa_moved_without_breaking_app_routes(): void
    {
        $this->get('/')->assertOk()->assertSee('Request a Demo')->assertSee('Explore Features')->assertSee('FICTIONAL SAMPLE DATA')
            ->assertSee('Bank Statement Income Analysis')->assertSee('Document Integrity and Fraud Indicators')->assertSee('Appraisal Review and Rental/DSCR Analysis');
        foreach (['/dashboard', '/login', '/register', '/files/1', '/settings/profile', '/platform', '/platform/inquiries'] as $path) {
            $r = $this->get($path)->assertOk();
            $this->assertStringContainsString('<div id="app"></div>', $r->getContent(), $path);
            $r->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
        $this->assertSame('/dashboard', config('fortify.home'));
    }

    public function test_signed_in_visitors_see_open_dashboard(): void
    {
        $u = $this->orgUser('owner');
        $html = $this->actingAs($u)->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="/dashboard" class="site-btn site-btn-primary">Open Dashboard', $html);
        $this->assertStringNotContainsString('>Sign In<', $html);
        $this->actingAs($u)->getJson('/api/me')->assertOk();
    }

    public function test_pricing_is_contact_only_until_approved_plans_are_published(): void
    {
        $this->seed();
        $html = $this->get('/pricing')->assertOk()->assertSee('Contact for pricing')->assertSee('Contact Sales')->getContent();
        $this->assertDoesNotMatchRegularExpression('~\$\s?\d~', $html);
        foreach (['Buy now', 'Free trial', 'Subscribe', 'Checkout'] as $bad) {
            $this->assertStringNotContainsStringIgnoringCase($bad, $html);
        }
        $this->assertStringNotContainsString('49.00', $html);
        config(['website.pricing.plans' => [['name' => 'Team', 'price_label' => 'Approved label', 'approved' => true]]]);
        $this->get('/pricing')->assertSee('Contact for pricing'); // not published yet
        config(['website.pricing.published' => true, 'website.pricing.plans' => [['name' => 'Unapproved', 'price_label' => 'X', 'approved' => false]]]);
        $this->get('/pricing')->assertSee('Contact for pricing')->assertDontSee('Unapproved');
        config(['website.pricing.plans' => [['name' => 'Team', 'price_label' => 'Approved label', 'approved' => true, 'includes' => ['Item A']]]]);
        $this->get('/pricing')->assertSee('Approved label')->assertSee('Item A')->assertDontSee('Contact for pricing');
    }

    public function test_sitemap_lists_only_public_pages_and_robots_is_noindex_outside_production(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        foreach (['/features', '/how-it-works', '/pricing', '/security', '/faq', '/contact'] as $p) {
            $this->assertStringContainsString('<loc>'.config('website.url').$p.'</loc>', $xml);
        }
        foreach (['login', 'register', 'dashboard', 'files', 'settings', 'platform', 'api', 'horizon', 'privacy', 'terms'] as $private) {
            $this->assertStringNotContainsString('/'.$private, $xml);
        }
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
        config(['website.indexing' => true]);
        $this->get('/robots.txt')->assertSee('Disallow: /')->assertDontSee('Allow: /'); // still testing env
        $this->get('/features')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('content="noindex,nofollow"', false);
    }

    public function test_production_with_indexing_allows_public_pages_but_never_app_or_draft_legal(): void
    {
        config(['website.indexing' => true]);
        $this->app['env'] = 'production';
        $this->get('/features')->assertOk()->assertHeaderMissing('X-Robots-Tag')->assertSee('content="index,follow"', false);
        $this->get('/robots.txt')->assertSee('Allow: /')->assertSee('Disallow: /dashboard')->assertSee('Sitemap: '.config('website.url').'/sitemap.xml');
        $this->get('/privacy')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/dashboard')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->app['env'] = 'testing';
    }

    public function test_public_html_contains_no_private_information_or_trackers(): void
    {
        $this->seed();
        config(['website.inquiry_notify_email' => 'inbox-secret-routing@example.test']);
        foreach (array_keys(self::PAGES) as $path) {
            $html = $this->get($path)->getContent();
            foreach ([config('app.key'), 'inbox-secret-routing@example.test', 'OPENAI_API_KEY', 'OCROLUS', 'BILLING_WEBHOOK_SECRET', 'DB_PASSWORD', 'googletagmanager', 'google-analytics', 'gtag(', 'fbq(', 'SOC 2', 'HIPAA', 'bank-grade', 'testimonial', 'never leave'] as $needle) {
                $this->assertStringNotContainsStringIgnoringCase((string) $needle, $html, "$path leaked/claimed $needle");
            }
            $this->assertDoesNotMatchRegularExpression('~\d+(\.\d+)?\s?% (accura|faster|time)~i', $html);
            $this->assertDoesNotMatchRegularExpression('~<(a|button)[^>]*>[^<]*Connect (a )?Bank~i', $html, 'no working Connect Bank CTA');
        }
    }

    public function test_footer_contact_details_only_when_configured(): void
    {
        $this->get('/')->assertDontSee('mailto:', false);
        config(['website.contact.email' => 'hello@example.test', 'website.contact.phone' => '+1 555 0100']);
        $this->get('/')->assertSee('mailto:hello@example.test', false)->assertSee('+1 555 0100');
    }

    public function test_contact_validation_rejects_bad_input_and_saves_nothing(): void
    {
        $this->from('/contact')->post('/contact', [])->assertRedirect('/contact')->assertSessionHasErrors(['name', 'email', 'company', 'inquiry_type', 'message']);
        $this->from('/contact')->post('/contact', $this->valid(['email' => 'not-an-email', 'inquiry_type' => 'loan_application', 'message' => 'short']))->assertSessionHasErrors(['email', 'inquiry_type', 'message']);
        $this->from('/contact')->post('/contact', $this->valid(['message' => str_repeat('a', 5001)]))->assertSessionHasErrors(['message']);
        $this->assertSame(0, WebsiteInquiry::count());
        $html = $this->get('/contact')->getContent();
        $this->assertStringNotContainsString('type="file"', $html);
        $this->assertStringNotContainsString('enctype="multipart', $html);
        $this->assertDoesNotMatchRegularExpression('~name="(account|routing|ssn|bank)~i', $html);
    }

    public function test_valid_submission_is_saved_before_success_and_notification_is_queued(): void
    {
        Bus::fake();
        config(['website.inquiry_notify_email' => 'sales@example.test']);
        $this->from('/contact')->post('/contact', $this->valid())->assertRedirect('/contact')->assertSessionHas('inquiry_saved', true);
        $i = WebsiteInquiry::sole();
        $this->assertSame(['Fictional Prospect', 'prospect@example.test', 'Example Lending Co', 'Processor', 'demo', 'new', false, 'queued'], [$i->name, $i->email, $i->company, $i->role, $i->inquiry_type, $i->status, $i->is_spam, $i->notification_status]);
        $this->assertNotSame('127.0.0.1', $i->source_hash);
        Bus::assertDispatched(SendWebsiteInquiryNotification::class, fn ($j) => $j->inquiryId === $i->id);
        $this->withSession(['inquiry_saved' => true])->get('/contact')->assertSee('your message has been received');
    }

    public function test_notification_job_sends_to_business_inbox_and_records_status(): void
    {
        Mail::fake();
        config(['website.inquiry_notify_email' => 'sales@example.test']);
        $this->post('/contact', $this->valid());
        $i = WebsiteInquiry::sole();
        Mail::assertSent(WebsiteInquiryReceived::class, fn ($m) => $m->hasTo('sales@example.test') && $m->hasReplyTo('prospect@example.test'));
        $this->assertSame('sent', $i->notification_status);
        $this->assertNotNull($i->notified_at);
    }

    public function test_notification_failure_never_loses_submission_or_exposes_error(): void
    {
        config(['website.inquiry_notify_email' => 'sales@example.test', 'mail.default' => 'failing']);
        config(['mail.mailers.failing' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);
        $r = $this->from('/contact')->post('/contact', $this->valid());
        $r->assertRedirect('/contact')->assertSessionHas('inquiry_saved', true)->assertSessionHasNoErrors();
        $i = WebsiteInquiry::sole();
        $this->assertSame('failed', $i->notification_status);
        $this->assertNotEmpty($i->notification_error);
        $this->get('/contact')->assertDontSee('Connection')->assertDontSee('Exception');
    }

    public function test_missing_notification_setup_still_saves_submission(): void
    {
        Bus::fake();
        config(['website.inquiry_notify_email' => null]);
        $this->post('/contact', $this->valid())->assertSessionHas('inquiry_saved', true);
        $this->assertSame('not_configured', WebsiteInquiry::sole()->notification_status);
        Bus::assertNothingDispatched();
    }

    public function test_honeypot_and_link_spam_are_flagged_and_not_notified(): void
    {
        Bus::fake();
        config(['website.inquiry_notify_email' => 'sales@example.test']);
        $this->post('/contact', $this->valid(['homepage_url' => 'http://spam.example']))->assertSessionHas('inquiry_saved', true);
        $this->post('/contact', $this->valid(['email' => 'b@example.test', 'message' => 'see http://a.example http://b.example http://c.example http://d.example']));
        $this->assertSame(['honeypot', 'link_heavy'], WebsiteInquiry::orderBy('id')->pluck('spam_reason')->all());
        $this->assertSame(2, WebsiteInquiry::where('is_spam', true)->where('notification_status', 'skipped_spam')->count());
        Bus::assertNothingDispatched();
    }

    public function test_contact_form_is_rate_limited(): void
    {
        Bus::fake();
        RateLimiter::clear('website-inquiry');
        config(['website.inquiry_rate_limit.per_minute' => 2]);
        $this->from('/contact')->post('/contact', $this->valid())->assertSessionHas('inquiry_saved');
        $this->from('/contact')->post('/contact', $this->valid())->assertSessionHas('inquiry_saved');
        $this->from('/contact')->post('/contact', $this->valid())->assertRedirect('/contact')->assertSessionHasErrors('form');
        $this->assertSame(2, WebsiteInquiry::count());
    }

    public function test_inquiry_admin_is_restricted_to_platform_staff(): void
    {
        $inquiry = WebsiteInquiry::create($this->valid() + ['status' => 'new']);
        unset($inquiry->homepage_url);
        $this->getJson('/api/platform/inquiries')->assertUnauthorized();
        foreach (['owner', 'admin', 'underwriter', 'processor', 'broker'] as $role) {
            $u = $this->orgUser($role);
            $this->actingAs($u)->getJson('/api/platform/inquiries')->assertForbidden();
            $this->actingAs($u)->getJson('/api/platform/inquiries/'.$inquiry->id)->assertForbidden();
            $this->actingAs($u)->getJson('/api/platform/inquiries/999999')->assertForbidden();
            $this->actingAs($u)->patchJson('/api/platform/inquiries/'.$inquiry->id, ['status' => 'closed'])->assertForbidden();
            $this->actingAs($u)->postJson('/api/platform/inquiries/'.$inquiry->id.'/notes', ['body' => 'x'])->assertForbidden();
        }
        $unverified = User::factory()->create(['platform_admin' => true, 'email_verified_at' => null]);
        $this->actingAs($unverified)->getJson('/api/platform/inquiries')->assertForbidden();
        $this->assertSame('new', $inquiry->fresh()->status);
    }

    public function test_platform_staff_can_filter_update_status_mark_spam_and_add_notes(): void
    {
        $base = $this->valid();
        unset($base['homepage_url']);
        $a = WebsiteInquiry::create($base + ['inquiry_type' => 'demo']);
        $b = WebsiteInquiry::create(array_replace($base, ['inquiry_type' => 'pricing', 'status' => 'closed']));
        $s = WebsiteInquiry::create($base + ['is_spam' => true, 'spam_reason' => 'honeypot']);
        $staff = User::factory()->create(['platform_admin' => true]);
        $this->actingAs($staff);
        $ids = fn ($q) => collect($this->getJson('/api/platform/inquiries'.$q)->assertOk()->json('inquiries.data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$a->id, $b->id], $ids(''));
        $this->assertSame([$b->id], $ids('?type=pricing'));
        $this->assertSame([$b->id], $ids('?status=closed'));
        $this->assertSame([$s->id], $ids('?spam=only'));
        $this->assertSame([$a->id, $b->id, $s->id], $ids('?spam=all'));
        $this->getJson('/api/platform/inquiries?status=bogus')->assertUnprocessable();
        $this->assertArrayNotHasKey('source_hash', $this->getJson('/api/platform/inquiries/'.$a->id)->assertOk()->json('inquiry'));
        $this->patchJson('/api/platform/inquiries/'.$a->id, ['status' => 'in_progress'])->assertOk()->assertJsonPath('inquiry.status', 'in_progress');
        $this->patchJson('/api/platform/inquiries/'.$a->id, ['status' => 'archived'])->assertUnprocessable();
        $this->patchJson('/api/platform/inquiries/'.$a->id, ['is_spam' => true])->assertOk()->assertJsonPath('inquiry.spam_reason', 'staff_marked');
        $this->patchJson('/api/platform/inquiries/'.$s->id, ['is_spam' => false])->assertOk()->assertJsonPath('inquiry.is_spam', false);
        $this->postJson('/api/platform/inquiries/'.$b->id.'/notes', ['body' => 'Called back; demo scheduled.'])->assertCreated()->assertJsonPath('inquiry.notes.0.body', 'Called back; demo scheduled.')->assertJsonPath('inquiry.notes.0.author.name', $staff->name);
        $this->postJson('/api/platform/inquiries/'.$b->id.'/notes', ['body' => ''])->assertUnprocessable();
        $this->getJson('/api/platform/inquiries/999999')->assertNotFound();
        $this->assertDatabaseHas('audit_events', ['action' => 'website_inquiry.updated', 'actor_id' => $staff->id]);
    }
}
