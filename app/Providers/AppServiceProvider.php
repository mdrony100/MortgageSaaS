<?php

namespace App\Providers;

use App\Appraisal\Extractor;
use App\Appraisal\VisionExtractor;
use App\Bank\Providers\HybridExtractor;
use App\Bank\Providers\IntegrityProvider;
use App\Bank\Providers\NativeExtractor;
use App\Bank\Providers\OcrolusDetect;
use App\Bank\Providers\OpenAIExtractor;
use App\Bank\StatementExtractor;
use App\Billing\FakeProvider;
use App\Billing\UnavailableProvider;
use App\Contracts\EmailDelivery;
use App\Contracts\PrivateFileStorage;
use App\Contracts\SubscriptionBilling;
use App\Exceptions\MailDeliveryException;
use App\Models\Document;
use App\Models\ImpersonationSession;
use App\Models\LoanFile;
use App\Policies\DocumentPolicy;
use App\Policies\LoanFilePolicy;
use App\Services\Audit;
use App\Services\DisabledProvider;
use App\Services\Impersonation;
use App\Services\PrivateStorage;
use App\Services\SmtpEmail;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Extractor::class, VisionExtractor::class);
        $this->app->bind(SubscriptionBilling::class, fn () => config('billing.provider') === 'fake' ? new FakeProvider : new UnavailableProvider);
        foreach (['DocumentExtraction', 'OpenAIAnalysis', 'FraudDetection', 'PlaidImport', 'DocumentClassification', 'MalwareScanner'] as $contract) {
            $this->app->bind('App\\Contracts\\'.$contract, DisabledProvider::class);
        }
        $this->app->bind(StatementExtractor::class, fn () => match (config('bank.extractor')) {
            'openai' => new OpenAIExtractor,'native' => new NativeExtractor,default => new HybridExtractor
        });
        $this->app->bind(IntegrityProvider::class, OcrolusDetect::class);
        $this->app->bind(PrivateFileStorage::class, PrivateStorage::class);
        $this->app->bind(EmailDelivery::class, SmtpEmail::class);
    }

    public function boot(): void
    {
        // After every provider has registered listeners, keep one verification sender.
        // An SMTP rejection must not roll the new account back into a failed registration.
        $this->app->booted(function () {
            Event::forget(Registered::class);
            Event::listen(Registered::class, function (Registered $event) {
                try {
                    $event->user->sendEmailVerificationNotification();
                } catch (MailDeliveryException $e) {
                    Log::warning('Registration continued after verification email was rejected', ['user_id' => $event->user->id, 'exception_type' => $e::class]);
                }
            });
        });
        Gate::policy(LoanFile::class, LoanFilePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        RateLimiter::for('auth-general', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('workspace', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('website-inquiry', fn (Request $r) => [
            Limit::perMinute(max(1, (int) config('website.inquiry_rate_limit.per_minute')))->by('inquiry-m:'.$r->ip())->response(fn () => back()->withInput()->withErrors(['form' => 'Too many requests. Please wait a few minutes and try again.'])),
            Limit::perDay(max(1, (int) config('website.inquiry_rate_limit.per_day')))->by('inquiry-d:'.$r->ip())->response(fn () => back()->withInput()->withErrors(['form' => 'Too many requests. Please try again later.'])),
        ]);
        RateLimiter::for('owner-console', fn (Request $r) => Limit::perMinute(180)->by('owner:'.($r->user()?->id ?: $r->ip())));
        RateLimiter::for('owner-actions', fn (Request $r) => Limit::perMinute(30)->by('owner-act:'.($r->user()?->id ?: $r->ip())));
        RateLimiter::for('impersonation', fn (Request $r) => Limit::perMinute(10)->by('imp:'.($r->user()?->id ?: $r->ip())));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
        ResetPassword::createUrlUsing(fn ($user, $token) => config('app.url').'/reset-password?token='.$token.'&email='.urlencode($user->email));
        foreach (['TwoFactorAuthenticationEnabled', 'TwoFactorAuthenticationConfirmed', 'TwoFactorAuthenticationDisabled', 'RecoveryCodesGenerated', 'RecoveryCodeReplaced', 'PasswordUpdatedViaController'] as $event) {
            Event::listen('Laravel\\Fortify\\Events\\'.$event, fn ($e) => Audit::record('auth.'.class_basename($e)));
        }
        Event::listen(Login::class, function ($e) {
            if (Impersonation::$switching) {
                return; // identity switch for an owner support session is audited separately
            }
            $e->user->forceFill(['last_login_at' => now()])->saveQuietly();
            Audit::record('auth.login');
        });
        Event::listen(Logout::class, function ($e) {
            if (Impersonation::$switching) {
                return;
            }
            $imp = Impersonation::current();
            if ($imp) {
                ImpersonationSession::whereKey($imp['id'])->update(['ended_at' => now(), 'end_reason' => 'logout']);
                Audit::record('impersonation.ended', null, null, ['admin_id' => $imp['admin_id'], 'target_user_id' => $imp['target_id'], 'support_session_id' => $imp['id'], 'end_reason' => 'logout'], 'internal', (int) $imp['admin_id']);
            }
            Audit::record('auth.logout');
        });
        Event::listen(Verified::class, fn ($e) => Audit::record('auth.email_verified'));
        Event::listen(PasswordReset::class, function ($e) {
            DB::table('sessions')->where('user_id', $e->user->id)->delete();
            Audit::record('auth.password_reset');
        });
    }
}
