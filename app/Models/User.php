<?php

namespace App\Models;

use App\Exceptions\MailDeliveryException;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** Set when SMTP rejects or cannot accept the verification message. Not persisted. */
    public bool $verificationEmailFailed = false;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'platform_admin' => 'boolean', 'two_factor_confirmed_at' => 'datetime', 'disabled_at' => 'datetime', 'last_login_at' => 'datetime'];
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        try {
            $this->notify(new VerifyEmail);
            $this->verificationEmailFailed = false;
        } catch (\Throwable $e) {
            $this->verificationEmailFailed = true;
            Log::warning('Verification email could not be delivered', [
                'user_id' => $this->id,
                'exception_type' => $e::class,
                'smtp_rejected' => str_contains($e->getMessage(), '550'),
            ]);
            throw new MailDeliveryException('The verification email could not be delivered. Your account is saved. Use “Send verification link” to try again, or ask an administrator to resend it.');
        }
    }
}
