<?php

namespace App\Services;

use App\Bank\DocumentParser;
use App\Bank\Scanner;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Database settings override config() at boot, including when config is cached.
 * The environment values loaded into the config repository stay the fallback.
 * Secrets are encrypted with the Laravel encrypted cast (APP_KEY) and are never
 * returned to the browser.
 */
class PlatformSettings
{
    public const CACHE_KEY = 'platform.settings.rows';

    /** @var array<string, array{config: string, type: string, secret?: bool, rules: string}> */
    public const FIELDS = [
        'openai_approved' => ['config' => 'bank.openai.approved', 'type' => 'bool', 'rules' => 'sometimes|boolean'],
        'openai_api_key' => ['config' => 'bank.openai.key', 'type' => 'string', 'secret' => true, 'rules' => 'nullable|string|max:4000'],
        'openai_model' => ['config' => 'bank.openai.model', 'type' => 'string', 'rules' => 'nullable|string|max:80'],
        'openai_secondary_model' => ['config' => 'bank.openai.secondary_model', 'type' => 'string', 'rules' => 'nullable|string|max:80'],
        'appraisal_vision_approved' => ['config' => 'review.vision_approved', 'type' => 'bool', 'rules' => 'sometimes|boolean'],
        'appraisal_vision_model' => ['config' => 'review.model', 'type' => 'string', 'rules' => 'nullable|string|max:80'],
        'integrity_approved' => ['config' => 'bank.integrity.approved', 'type' => 'bool', 'rules' => 'sometimes|boolean'],
        'integrity_provider' => ['config' => 'bank.integrity.provider', 'type' => 'string', 'rules' => 'sometimes|in:disabled,ocrolus'],
        'ocrolus_token' => ['config' => 'bank.integrity.token', 'type' => 'string', 'secret' => true, 'rules' => 'nullable|string|max:4000'],
        'billing_provider' => ['config' => 'billing.provider', 'type' => 'string', 'rules' => 'sometimes|in:disabled,fake,stripe'],
        'billing_mode' => ['config' => 'billing.mode', 'type' => 'string', 'rules' => 'sometimes|in:test,live'],
        'billing_secret' => ['config' => 'billing.secret', 'type' => 'string', 'secret' => true, 'rules' => 'nullable|string|max:4000'],
        'billing_webhook_secret' => ['config' => 'billing.webhook_secret', 'type' => 'string', 'secret' => true, 'rules' => 'nullable|string|max:4000'],
        'mail_mailer' => ['config' => 'mail.default', 'type' => 'string', 'rules' => 'sometimes|in:smtp,log,sendmail,array'],
        'mail_host' => ['config' => 'mail.mailers.smtp.host', 'type' => 'string', 'rules' => 'nullable|string|max:255'],
        'mail_port' => ['config' => 'mail.mailers.smtp.port', 'type' => 'int', 'rules' => 'nullable|integer|min:1|max:65535'],
        'mail_username' => ['config' => 'mail.mailers.smtp.username', 'type' => 'string', 'rules' => 'nullable|string|max:255'],
        'mail_password' => ['config' => 'mail.mailers.smtp.password', 'type' => 'string', 'secret' => true, 'rules' => 'nullable|string|max:4000'],
        'mail_scheme' => ['config' => 'mail.mailers.smtp.scheme', 'type' => 'string', 'rules' => 'nullable|string|max:20'],
        'mail_from_address' => ['config' => 'mail.from.address', 'type' => 'string', 'rules' => 'nullable|email|max:255'],
        'mail_from_name' => ['config' => 'mail.from.name', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
        'storage_disk' => ['config' => 'mortgage.private_disk', 'type' => 'string', 'rules' => 'sometimes|in:private,s3'],
        's3_key' => ['config' => 'filesystems.disks.s3.key', 'type' => 'string', 'secret' => true, 'rules' => 'nullable|string|max:4000'],
        's3_secret' => ['config' => 'filesystems.disks.s3.secret', 'type' => 'string', 'secret' => true, 'rules' => 'nullable|string|max:4000'],
        's3_region' => ['config' => 'filesystems.disks.s3.region', 'type' => 'string', 'rules' => 'nullable|string|max:40'],
        's3_bucket' => ['config' => 'filesystems.disks.s3.bucket', 'type' => 'string', 'rules' => 'nullable|string|max:120'],
        's3_endpoint' => ['config' => 'filesystems.disks.s3.endpoint', 'type' => 'string', 'rules' => 'nullable|string|max:255'],
        's3_path_style' => ['config' => 'filesystems.disks.s3.use_path_style_endpoint', 'type' => 'bool', 'rules' => 'sometimes|boolean'],
        'clamav_path' => ['config' => 'bank.clamav', 'type' => 'string', 'rules' => 'nullable|string|max:255'],
        'bank_python' => ['config' => 'bank.python', 'type' => 'string', 'rules' => 'nullable|string|max:255'],
        'parser_sandbox' => ['config' => 'bank.sandbox', 'type' => 'string', 'rules' => 'sometimes|in:bwrap,none'],
        'website_contact_email' => ['config' => 'website.contact.email', 'type' => 'string', 'rules' => 'nullable|email|max:255'],
        'website_contact_phone' => ['config' => 'website.contact.phone', 'type' => 'string', 'rules' => 'nullable|string|max:40'],
        'website_legal_company_name' => ['config' => 'website.legal.company_name', 'type' => 'string', 'rules' => 'nullable|string|max:160'],
        'impersonation_enabled' => ['config' => 'platform.impersonation.enabled', 'type' => 'bool', 'rules' => 'sometimes|boolean'],
        'impersonation_minutes' => ['config' => 'platform.impersonation.minutes', 'type' => 'int', 'rules' => 'nullable|integer|min:5|max:240'],
        'impersonation_allow_document_download' => ['config' => 'platform.impersonation.allow_document_download', 'type' => 'bool', 'rules' => 'sometimes|boolean'],
    ];

    /** Environment values captured before the first database overlay. */
    private ?array $baseline = null;

    public function rules(): array
    {
        $rules = [];
        foreach (self::FIELDS as $key => $field) {
            $rules[$key] = $field['rules'];
            if (! empty($field['secret'])) {
                $rules['clear_'.$key] = 'sometimes|boolean';
            }
        }

        return $rules;
    }

    /**
     * Treat the current config repository as the environment fallback.
     * Boot calls apply() once, which captures this automatically. Tests use it
     * when they change config() to stand in for a different environment.
     */
    public function recapture(): void
    {
        $this->baseline = null;
        $this->capture();
    }

    public function apply(bool $fresh = false): void
    {
        if ($this->baseline === null) {
            $this->capture();
        }
        foreach ($this->baseline as $path => $value) {
            config([$path => $value]);
        }
        $this->overlay($this->rows($fresh));
    }

    /** @param  array<string, mixed>  $input */
    public function update(#[\SensitiveParameter] array $input): array
    {
        $rows = $this->rows(true);
        $changed = [];
        foreach (self::FIELDS as $key => $field) {
            if (! empty($field['secret'])) {
                if (isset($input[$key]) && is_string($input[$key]) && trim($input[$key]) !== '') {
                    $plain = trim($input[$key]);
                    $current = isset($rows[$key]) ? $this->plain($rows[$key], $key) : null;
                    if ($current !== $plain) {
                        $this->storeSecret($key, $plain);
                        $changed[] = $key;
                    }
                } elseif (! empty($input['clear_'.$key]) && isset($rows[$key])) {
                    PlatformSetting::query()->where('key', $key)->delete();
                    $changed[] = $key;
                }

                continue;
            }
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $stored = $this->encode($field['type'], $input[$key]);
            if ($stored === null) {
                if (isset($rows[$key])) {
                    PlatformSetting::query()->where('key', $key)->delete();
                    $changed[] = $key;
                }

                continue;
            }
            $current = isset($rows[$key]) ? (string) $this->plain($rows[$key], $key) : null;
            if ($current === $stored) {
                continue;
            }
            PlatformSetting::query()->updateOrCreate(['key' => $key], [
                'value' => $stored, 'secret_value' => null, 'secret' => false, 'updated_by' => auth()->id(),
            ]);
            $changed[] = $key;
        }
        if ($changed !== []) {
            Audit::record('admin.settings.updated', null, null, ['changed' => $changed], 'internal');
            $this->apply(true);
        }

        return $changed;
    }

    public function view(): array
    {
        $rows = $this->rows(false);
        $out = [];
        foreach (self::FIELDS as $key => $field) {
            if (! empty($field['secret'])) {
                $text = '';
                if (isset($rows[$key])) {
                    $text = (string) ($this->plain($rows[$key], $key) ?? '');
                    $source = 'database';
                } else {
                    $env = config($field['config']);
                    $text = is_string($env) ? $env : '';
                    $source = $text === '' ? 'none' : 'environment';
                }
                $out[$key] = [
                    'secret' => true,
                    'set' => $text !== '',
                    'last4' => $text === '' ? null : substr($text, -4),
                    'source' => $source,
                ];

                continue;
            }
            $out[$key] = [
                'secret' => false,
                'value' => $this->present($key, $field),
                'source' => isset($rows[$key]) ? 'database' : 'environment',
            ];
        }

        return $out;
    }

    public function probe(string $check): array
    {
        return match ($check) {
            'smtp' => $this->probeSmtp(),
            'storage' => $this->probeStorage(),
            'openai' => $this->probeOpenAi(),
            'clamav' => $this->probeClamAv(),
            'python' => $this->probePython(),
            default => ['ok' => false, 'message' => 'Unknown check.'],
        };
    }

    public function sendTestEmail(User $user): array
    {
        $mailer = (string) config('mail.default', 'log');
        try {
            config(['mail.mailers.smtp.timeout' => 10]);
            Mail::purge($mailer);
            Mail::mailer($mailer)->raw(
                'This is a MortgageLens settings test. No customer data is included.',
                function ($message) use ($user) {
                    $message->to($user->email)->subject('MortgageLens test email');
                }
            );
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'The test email could not be sent. Check the host, port, and password, then try again.'];
        }

        return ['ok' => true, 'message' => 'Test email handed to the '.$mailer.' mailer for your sign-in address.'];
    }

    private function capture(): void
    {
        $this->baseline = [];
        $paths = array_map(fn (array $field) => $field['config'], self::FIELDS);
        $paths[] = 'billing.fake_enabled';
        foreach (array_unique($paths) as $path) {
            $this->baseline[$path] = config($path);
        }
    }

    /** @param  array<string, array{secret: bool, value: ?string, secret_value: ?string}>  $rows */
    private function overlay(array $rows): void
    {
        foreach (self::FIELDS as $key => $field) {
            if (! isset($rows[$key])) {
                continue;
            }
            $plain = $this->plain($rows[$key], $key);
            if ($plain === null && empty($field['secret'])) {
                continue;
            }
            if (! empty($field['secret'])) {
                if (is_string($plain) && $plain !== '') {
                    config([$field['config'] => $plain]);
                }

                continue;
            }
            if ($key === 'parser_sandbox') {
                config(['bank.sandbox' => $plain === 'none' ? 'none' : 'bwrap']);

                continue;
            }
            if ($key === 'billing_mode') {
                config(['billing.mode' => $plain, 'billing.fake_enabled' => $plain === 'test']);

                continue;
            }
            if ($field['type'] === 'bool') {
                config([$field['config'] => in_array((string) $plain, ['1', 'true'], true)]);

                continue;
            }
            if ($field['type'] === 'int') {
                $n = (int) $plain;
                if ($key === 'impersonation_minutes') {
                    $n = max(5, min(240, $n));
                }
                config([$field['config'] => $n]);

                continue;
            }
            config([$field['config'] => $plain]);
        }
    }

    /** @param  array{config: string, type: string}  $field */
    private function present(string $key, array $field): mixed
    {
        if ($key === 'parser_sandbox') {
            return config('bank.sandbox') === 'none' ? 'none' : 'bwrap';
        }
        if ($key === 'billing_mode') {
            $mode = config('billing.mode');

            return in_array($mode, ['test', 'live'], true)
                ? $mode
                : ((config('billing.provider') === 'fake' || config('billing.fake_enabled')) ? 'test' : 'live');
        }
        $value = config($field['config']);
        if ($field['type'] === 'bool') {
            return (bool) $value;
        }
        if ($field['type'] === 'int') {
            return ($value === null || $value === '') ? null : (int) $value;
        }

        return $value === null ? '' : $value;
    }

    private function encode(string $type, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($type === 'bool') {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
        }

        return (string) $value;
    }

    private function storeSecret(string $key, #[\SensitiveParameter] string $plain): void
    {
        $row = PlatformSetting::query()->firstOrNew(['key' => $key]);
        $row->secret = true;
        $row->value = null;
        $row->secret_value = $plain;
        $row->updated_by = auth()->id();
        $row->save();
    }

    /** @return array<string, array{secret: bool, value: ?string, secret_value: ?string}> */
    private function rows(bool $fresh): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }
        try {
            if (! Schema::hasTable('platform_settings')) {
                return [];
            }
        } catch (\Throwable) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, 30, function () {
            return DB::table('platform_settings')->get(['key', 'value', 'secret_value', 'secret'])
                ->mapWithKeys(fn ($row) => [$row->key => [
                    'secret' => (bool) $row->secret,
                    'value' => $row->value,
                    'secret_value' => $row->secret_value,
                ]])->all();
        });
    }

    /** @param  array{secret: bool, value: ?string, secret_value: ?string}  $row */
    private function plain(array $row, ?string $key = null): ?string
    {
        if ($row['secret']) {
            if ($row['secret_value'] === null || $row['secret_value'] === '') {
                return null;
            }
            try {
                return Crypt::decryptString($row['secret_value']);
            } catch (\Throwable) {
                Log::warning('Platform setting could not be decrypted', ['key' => $key]);

                return null;
            }
        }

        return $row['value'];
    }

    private function probeSmtp(): array
    {
        $mailer = (string) config('mail.default', 'log');
        if (in_array($mailer, ['log', 'array', 'sendmail'], true)) {
            return ['ok' => true, 'message' => 'Mailer "'.$mailer.'" does not need a network connection.'];
        }
        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');
        if ($host === '' || $port < 1) {
            return ['ok' => false, 'message' => 'SMTP host or port is not set.'];
        }
        $errno = 0;
        $err = '';
        $fp = @fsockopen($host, $port, $errno, $err, 5);
        if (! is_resource($fp)) {
            return ['ok' => false, 'message' => 'Could not connect to '.$host.':'.$port.'.'];
        }
        fclose($fp);

        return ['ok' => true, 'message' => 'Connected to '.$host.':'.$port.'.'];
    }

    private function probeStorage(): array
    {
        $disk = (string) config('mortgage.private_disk', 'private');
        $name = '.settings-probe-'.Str::uuid();
        try {
            Storage::purge($disk);
            Storage::disk($disk)->put($name, 'ok');
            if (! Storage::disk($disk)->exists($name)) {
                return ['ok' => false, 'message' => 'Storage disk "'.$disk.'" did not keep the test file.'];
            }

            return ['ok' => true, 'message' => 'Storage disk "'.$disk.'" accepted a test file and it was removed.'];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Storage check failed for disk "'.$disk.'".'];
        } finally {
            try {
                Storage::disk($disk)->delete($name);
            } catch (\Throwable) {
            }
        }
    }

    private function probeOpenAi(): array
    {
        $key = config('bank.openai.key');
        if (! is_string($key) || $key === '') {
            return ['ok' => false, 'message' => 'No OpenAI API key is set.'];
        }
        try {
            $response = Http::withToken($key)->timeout(8)->acceptJson()->get('https://api.openai.com/v1/models');
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'OpenAI could not be reached.'];
        }
        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'OpenAI returned HTTP '.$response->status().'.'];
        }
        $count = count($response->json('data') ?? []);

        return ['ok' => true, 'message' => 'OpenAI accepted the key and listed '.$count.' models.'];
    }

    private function probeClamAv(): array
    {
        $path = (string) config('bank.clamav', 'clamscan');
        $ok = app(Scanner::class)->installed();

        return ['ok' => $ok, 'message' => $ok
            ? 'ClamAV binary is present ('.$path.').'
            : 'ClamAV binary was not found ('.$path.'). Uploads stay Unscanned until it is installed.'];
    }

    private function probePython(): array
    {
        $report = app(DocumentParser::class)->diagnose();
        $detail = trim(implode(' ', $report['issues']).' '.($report['warning'] ?? ''));
        if ($report['issues'] !== []) {
            return ['ok' => false, 'message' => $detail];
        }
        $version = $report['python'] ? 'Python '.$report['python'].' and PyMuPDF are ready.' : 'Python and PyMuPDF are ready.';
        $sandbox = $report['sandbox'] === 'none' ? ' Parser sandbox is off.' : ' Parser sandbox is bubblewrap.';

        return ['ok' => true, 'message' => trim($version.$sandbox.' '.($report['warning'] ?? ''))];
    }
}
