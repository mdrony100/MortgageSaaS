<?php

namespace Tests\Feature;

use App\Bank\ParserConfigurationException;
use App\Models\AuditEvent;
use App\Models\ImpersonationSession;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['platform.console_require_2fa' => true]);
    }

    public function test_secrets_are_encrypted_hidden_and_override_cached_config_until_cleared(): void
    {
        config(['bank.openai.key' => 'sk-env-secret-ZZZZ', 'website.contact.email' => 'env@example.test', 'website.legal.company_name' => 'Env Co']);
        app(PlatformSettings::class)->recapture();

        $admin = $this->admin();
        $customer = User::factory()->create();
        $this->actingAs($customer)->getJson('/api/admin/settings')->assertForbidden();
        $this->actingAs($customer)->putJson('/api/admin/settings', ['website_contact_phone' => '555'])->assertForbidden();
        $this->assertSame(0, DB::table('platform_settings')->count());

        $secret = 'sk-db-secret-ABCD';
        $response = $this->actingAs($admin)->putJson('/api/admin/settings', [
            'openai_api_key' => $secret,
            'website_contact_email' => 'settings-contact@example.test',
            'website_legal_company_name' => 'DB Legal LLC',
            'openai_approved' => true,
        ])->assertOk();
        $this->assertStringNotContainsString($secret, $response->getContent());
        $this->assertArrayNotHasKey('value', $response->json('settings.openai_api_key'));
        $response->assertJsonPath('settings.openai_api_key.set', true)
            ->assertJsonPath('settings.openai_api_key.last4', 'ABCD')
            ->assertJsonPath('settings.openai_api_key.source', 'database')
            ->assertJsonPath('settings.website_contact_email.value', 'settings-contact@example.test');
        $this->assertSame($secret, config('bank.openai.key'));
        $this->assertTrue(config('bank.openai.approved'));

        $stored = DB::table('platform_settings')->where('key', 'openai_api_key')->first();
        $this->assertNotNull($stored->secret_value);
        $this->assertStringNotContainsString($secret, $stored->secret_value);
        $this->assertSame($secret, Crypt::decryptString($stored->secret_value));
        $this->assertSame($secret, PlatformSetting::find('openai_api_key')->secret_value);
        $cached = json_encode(Cache::get(PlatformSettings::CACHE_KEY));
        $this->assertStringNotContainsString($secret, $cached);

        $audit = DB::table('audit_events')->where('action', 'admin.settings.updated')->value('metadata');
        $this->assertStringNotContainsString($secret, $audit);
        $this->assertStringNotContainsString('settings-contact@example.test', $audit);
        $this->assertContains('openai_api_key', AuditEvent::where('action', 'admin.settings.updated')->first()->metadata['changed']);

        $this->get('/')->assertSee('settings-contact@example.test', false)->assertSee('DB Legal LLC');

        $this->putJson('/api/admin/settings', ['clear_openai_api_key' => true])->assertOk()
            ->assertJsonPath('settings.openai_api_key.source', 'environment')
            ->assertJsonPath('settings.openai_api_key.last4', 'ZZZZ');
        $this->assertSame('sk-env-secret-ZZZZ', config('bank.openai.key'));
        $this->assertNull(DB::table('platform_settings')->where('key', 'openai_api_key')->value('secret_value'));
        $cleared = DB::table('audit_events')->where('action', 'admin.settings.updated')->orderByDesc('id')->value('metadata');
        $this->assertStringNotContainsString($secret, $cleared);
        $this->assertStringNotContainsString('sk-env-secret-ZZZZ', $cleared);
    }

    public function test_saved_settings_can_turn_support_sessions_on_when_the_environment_has_them_off(): void
    {
        config(['platform.impersonation.enabled' => false, 'platform.impersonation.minutes' => 30, 'platform.impersonation.allow_document_download' => false]);
        app(PlatformSettings::class)->recapture();
        $admin = $this->admin();
        $customer = User::factory()->create();
        $this->actingAs($admin)->postJson('/api/admin/impersonation', ['user_id' => $customer->id, 'reason' => 'Customer asked for help with an upload'])->assertForbidden();

        $this->putJson('/api/admin/settings', [
            'impersonation_enabled' => true,
            'impersonation_minutes' => 15,
            'impersonation_allow_document_download' => true,
        ])->assertOk();
        $this->assertTrue(config('platform.impersonation.enabled'));
        $this->assertSame(15, config('platform.impersonation.minutes'));
        $this->assertTrue(config('platform.impersonation.allow_document_download'));
        $this->putJson('/api/admin/settings', ['impersonation_minutes' => 1])->assertUnprocessable();

        $other = $this->admin();
        $this->postJson('/api/admin/impersonation', ['user_id' => $other->id, 'reason' => 'Trying to view another platform admin'])->assertUnprocessable();
        $this->postJson('/api/admin/impersonation', ['user_id' => $customer->id, 'reason' => 'Customer asked for help with an upload'])->assertOk()->assertJsonPath('redirect', '/dashboard');
        $session = ImpersonationSession::first();
        $this->assertSame($admin->id, $session->admin_id);
        $this->assertSame($customer->id, $session->target_user_id);
    }

    public function test_connection_checks_do_not_echo_secrets_and_parser_health_is_visible(): void
    {
        config(['bank.openai.key' => 'sk-test-secret-ABCD', 'bank.python' => '']);
        app(PlatformSettings::class)->recapture();
        Http::fake(['api.openai.com/*' => Http::response(['data' => [['id' => 'gpt-test']], 'echo' => 'sk-test-secret-ABCD'], 200)]);
        $admin = $this->admin();
        $openai = $this->actingAs($admin)->postJson('/api/admin/settings/test', ['check' => 'openai'])->assertOk();
        $this->assertTrue($openai->json('ok'));
        $this->assertStringNotContainsString('sk-test-secret-ABCD', $openai->getContent());
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-test-secret-ABCD'));

        $parser = $this->postJson('/api/admin/settings/test', ['check' => 'python'])->assertOk();
        $this->assertFalse($parser->json('ok'));
        $this->assertStringContainsString('Python path is empty.', $parser->json('message'));

        $page = $this->getJson('/api/admin/settings')->assertOk();
        $this->assertContains('Python path is empty.', $page->json('parser.issues'));
        $this->assertStringNotContainsString('sk-test-secret-ABCD', $page->getContent());

        $this->postJson('/api/admin/settings/test-email')->assertOk()->assertJsonPath('ok', true);

        $dir = sys_get_temp_dir().'/settings-disk-'.bin2hex(random_bytes(4));
        mkdir($dir);
        config([
            'filesystems.disks.probe' => ['driver' => 'local', 'root' => $dir, 'throw' => true],
            'mortgage.private_disk' => 'probe',
        ]);
        app(PlatformSettings::class)->recapture();
        $this->postJson('/api/admin/settings/test', ['check' => 'storage'])->assertOk()->assertJsonPath('ok', true);
        $this->assertSame([], array_values(array_diff(scandir($dir), ['.', '..'])));

        $this->actingAs($this->admin(false))->getJson('/api/admin/settings')->assertForbidden();
        $this->assertSame(ParserConfigurationException::CUSTOMER_MESSAGE, "Document reading isn't set up on this server yet, so contact support");
    }

    private function admin(bool $twoFactor = true): User
    {
        $user = User::factory()->create(['name' => 'Fictional Platform Admin']);
        $user->forceFill(['platform_admin' => true] + ($twoFactor ? ['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()] : []))->save();

        return $user;
    }
}
