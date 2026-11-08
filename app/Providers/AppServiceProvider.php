<?php

namespace App\Providers;

use App\Contracts\EmailDelivery;
use App\Contracts\PrivateFileStorage;
use App\Models\Document;
use App\Models\LoanFile;
use App\Policies\DocumentPolicy;
use App\Policies\LoanFilePolicy;
use App\Services\Audit;
use App\Services\DisabledProvider;
use App\Services\PrivateStorage;
use App\Services\SmtpEmail;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (['DocumentExtraction', 'OpenAIAnalysis', 'FraudDetection', 'SubscriptionBilling', 'PlaidImport', 'DocumentClassification', 'MalwareScanner'] as $contract) {
            $this->app->bind('App\\Contracts\\'.$contract, DisabledProvider::class);
        }
        $this->app->bind(PrivateFileStorage::class, PrivateStorage::class);
        $this->app->bind(EmailDelivery::class, SmtpEmail::class);
    }

    public function boot(): void
    {
        Gate::policy(LoanFile::class, LoanFilePolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        RateLimiter::for('auth-general', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('workspace', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(10)->by($r->user()?->id ?: $r->ip()));
        ResetPassword::createUrlUsing(fn ($user, $token) => config('app.url').'/reset-password?token='.$token.'&email='.urlencode($user->email));
        foreach (['TwoFactorAuthenticationEnabled', 'TwoFactorAuthenticationConfirmed', 'TwoFactorAuthenticationDisabled', 'RecoveryCodesGenerated', 'RecoveryCodeReplaced', 'PasswordUpdatedViaController'] as $event) {
            Event::listen('Laravel\\Fortify\\Events\\'.$event, fn ($e) => Audit::record('auth.'.class_basename($e)));
        }
        Event::listen(Login::class, fn ($e) => Audit::record('auth.login'));
        Event::listen(Logout::class, fn ($e) => Audit::record('auth.logout'));
        Event::listen(Verified::class, fn ($e) => Audit::record('auth.email_verified'));
        Event::listen(PasswordReset::class, function ($e) {
            DB::table('sessions')->where('user_id', $e->user->id)->delete();
            Audit::record('auth.password_reset');
        });
    }
}
