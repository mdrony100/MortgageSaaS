<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    private const LINKS = [
        '<link rel="icon" href="/favicon.ico" sizes="any">',
        '<link rel="icon" href="/favicon.svg" type="image/svg+xml">',
        '<link rel="icon" href="/favicon-32x32.png" type="image/png" sizes="32x32">',
        '<link rel="icon" href="/favicon-16x16.png" type="image/png" sizes="16x16">',
        '<link rel="apple-touch-icon" href="/apple-touch-icon.png" sizes="180x180">',
        '<link rel="manifest" href="/site.webmanifest">',
    ];

    private const FILES = [
        'favicon.ico' => 'image/x-icon',
        'favicon.svg' => 'image/svg+xml',
        'favicon-32x32.png' => 'image/png',
        'favicon-16x16.png' => 'image/png',
        'apple-touch-icon.png' => 'image/png',
        'site.webmanifest' => 'application/manifest+json',
    ];

    public function test_every_shell_links_the_favicon_set_and_the_files_are_real(): void
    {
        foreach (['site/layout.blade.php', 'app.blade.php', 'admin.blade.php', 'admin/denied.blade.php'] as $shell) {
            $this->assertStringContainsString("@include('partials.favicons')", file_get_contents(resource_path('views/'.$shell)), $shell);
        }

        foreach (['/' => 'marketing', '/login' => 'auth', '/dashboard' => 'customer'] as $path => $shell) {
            $this->assertLinks($this->get($path)->assertOk()->getContent(), $shell);
        }

        $customer = User::factory()->create();
        $this->assertLinks($this->actingAs($customer)->get('/admin')->assertForbidden()->getContent(), 'admin denied');

        $admin = User::factory()->create();
        $admin->forceFill(['platform_admin' => true])->save();
        $this->assertLinks($this->actingAs($admin)->get('/admin')->assertOk()->getContent(), 'admin');

        foreach (self::FILES as $file => $type) {
            $path = public_path($file);
            $this->assertFileExists($path);
            $this->assertGreaterThan(32, filesize($path), $file);
            $ignored = [];
            exec('git -C '.escapeshellarg(base_path()).' check-ignore -q -- '.escapeshellarg('public/'.$file), $ignored, $code);
            $this->assertSame(1, $code, "public/$file is gitignored");

            $response = $this->get('/'.$file)->assertOk();
            $this->assertStringContainsString($type, (string) $response->headers->get('Content-Type'));
            $cache = (string) $response->headers->get('Cache-Control');
            $this->assertStringContainsString('public', $cache);
            $this->assertStringContainsString('max-age=604800', $cache);
            $this->assertSame(file_get_contents($path), $response->streamedContent(), $file);
        }

        $svg = file_get_contents(public_path('favicon.svg'));
        $this->assertStringContainsString('#0f766e', $svg);
        $this->assertStringContainsString('<svg', $svg);

        $ico = file_get_contents(public_path('favicon.ico'));
        $this->assertSame("\x00\x00\x01\x00", substr($ico, 0, 4));
        $this->assertSame(2, unpack('v', substr($ico, 4, 2))[1]);

        foreach (['favicon-16x16.png' => 16, 'favicon-32x32.png' => 32, 'apple-touch-icon.png' => 180] as $file => $size) {
            $info = getimagesize(public_path($file));
            $this->assertSame($size, $info[0], $file);
            $this->assertSame($size, $info[1], $file);
            $this->assertSame(IMAGETYPE_PNG, $info[2], $file);
        }

        $png = imagecreatefrompng(public_path('favicon-32x32.png'));
        $teal = imagecolorsforindex($png, imagecolorat($png, 1, 16));
        $this->assertSame(['red' => 15, 'green' => 118, 'blue' => 110, 'alpha' => 0], $teal);

        $manifest = json_decode($this->get('/site.webmanifest')->streamedContent(), true);
        $this->assertSame('MortgageLens', $manifest['name']);
        $this->assertSame('#102840', $manifest['theme_color']);
        $this->assertSame('#0f766e', $manifest['background_color']);
        $srcs = array_column($manifest['icons'], 'src');
        $this->assertSame(['/favicon-16x16.png', '/favicon-32x32.png', '/apple-touch-icon.png'], $srcs);

        $htaccess = file_get_contents(public_path('.htaccess'));
        $this->assertStringContainsString('AddType image/svg+xml .svg', $htaccess);
        $this->assertStringContainsString('AddType application/manifest+json .webmanifest', $htaccess);
        $this->assertStringContainsString('RewriteRule ^(?:favicon\.ico|favicon\.svg|favicon-16x16\.png|favicon-32x32\.png|apple-touch-icon\.png|site\.webmanifest)$ - [L]', $htaccess);
    }

    private function assertLinks(string $html, string $shell): void
    {
        foreach (self::LINKS as $link) {
            $this->assertStringContainsString($link, $html, $shell);
        }
    }
}
