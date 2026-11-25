<?php

namespace App\Services;

use App\Jobs\SendPlatformEmail;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\PageCreditAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/** Queued plain-language notices. A mail failure is logged and never thrown. */
class NotificationMail
{
    public static function enabled(string $group): bool
    {
        return (bool) config('notifications.groups.'.$group, true);
    }

    public static function url(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    /** Configured admin inbox, or every platform admin when that inbox is empty. */
    public static function adminRecipients(): array
    {
        $inbox = config('website.inquiry_notify_email');
        if (filled($inbox)) {
            return [mb_strtolower(trim((string) $inbox))];
        }

        return User::query()->where('platform_admin', true)->whereNotNull('email')->pluck('email')
            ->map(fn ($email) => mb_strtolower((string) $email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function managers(int $organizationId): array
    {
        return Membership::query()
            ->where('organization_id', $organizationId)
            ->whereIn('role', ['owner', 'admin'])
            ->with('user:id,email')
            ->get()
            ->pluck('user.email')
            ->filter()
            ->map(fn ($email) => mb_strtolower((string) $email))
            ->unique()
            ->values()
            ->all();
    }

    public static function send(?string $to, string $subject, string $body, ?string $group = null, bool $afterCommit = true): bool
    {
        if (! is_string($to) || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return SendPlatformEmail::safely($to, $subject, $body, $group, $afterCommit);
    }

    /** @param  list<string|null>  $emails */
    public static function sendMany(array $emails, string $subject, string $body, ?string $group = null): void
    {
        foreach (array_unique(array_filter($emails)) as $email) {
            self::send($email, $subject, $body, $group);
        }
    }

    public static function welcome(User $user): void
    {
        $org = $user->memberships()->with('organization:id,name')->first()?->organization;
        $workspace = $org->name ?? 'your workspace';
        $body = "Hello {$user->name},\n\nWelcome to Non-QM Doc AI. Your workspace \"{$workspace}\" is ready.\n\nA separate message asks you to confirm this email address. After you confirm it, sign in to upload documents and review results.\n\n".self::url('/dashboard');
        self::send($user->email, 'Welcome to Non-QM Doc AI', $body, 'customer_account');
    }

    public static function signup(User $user): void
    {
        $org = $user->memberships()->with('organization:id,name')->first()?->organization;
        $body = "A new organization signed up.\n\nOrganization: ".($org->name ?? 'Unknown')."\nOwner: {$user->name}\nEmail: {$user->email}\n\n".self::url('/admin/organizations');
        self::sendMany(self::adminRecipients(), 'Non-QM Doc AI: new organization signup', $body, 'admin_signups');
    }

    public static function demoConfirmation(string $email, string $name, ?string $company): void
    {
        $who = $company ? " for {$company}" : '';
        $body = "Hello {$name},\n\nWe received your demo request{$who}. A member of the Non-QM Doc AI team will contact you at this email address. A demo is free and nothing is charged.\n\n".self::url('/request-demo');
        self::send($email, 'We received your Non-QM Doc AI demo request', $body, 'customer_account');
    }

    public static function paymentConfirmation(string $email, string $organization, string $what, string $amount): void
    {
        $body = "We received your request for {$what} for {$organization}.\n\nAmount shown: \${$amount}. Payment is arranged offline. Nothing has been charged. We will email you when the request is reviewed.\n\n".self::url('/settings/plans');
        self::send($email, 'Non-QM Doc AI: we received your request', $body, 'customer_billing');
    }

    public static function planChangeRequested(string $organization, string $planName): void
    {
        $body = "A customer asked to change the plan for {$organization} to {$planName}.\n\nNo payment has been taken. Review the request in the owner console.\n\n".self::url('/admin/approvals');
        self::sendMany(self::adminRecipients(), 'Non-QM Doc AI: new plan change request', $body, 'admin_billing');
    }

    public static function teamInvite(string $email, string $organization, string $url): bool
    {
        $body = "You are invited to the Non-QM Doc AI workspace \"{$organization}\".\n\nSign in with this email address, then open the invitation. The link expires in 7 days.\n\n{$url}";

        return self::send($email, 'Non-QM Doc AI workspace invitation', $body, 'customer_team', false);
    }

    public static function memberAdded(User $user, Organization $organization): void
    {
        $body = "Hello {$user->name},\n\nYou are now a member of the Non-QM Doc AI workspace \"{$organization->name}\".\n\nSign in to open the workspace.\n\n".self::url('/dashboard');
        self::send($user->email, 'You joined '.$organization->name.' on Non-QM Doc AI', $body, 'customer_team');
    }

    public static function fileShared(LoanFile $file, User $recipient, User $sharer): void
    {
        if ((int) $recipient->id === (int) $sharer->id || ! $recipient->email) {
            return;
        }
        $body = "Hello {$recipient->name},\n\n{$sharer->name} shared a loan file with you in Non-QM Doc AI.\n\nFile: {$file->name}\n\nSign in to open it. This email does not include document contents.\n\n".self::url('/files/'.$file->id);
        self::send($recipient->email, 'A loan file was shared with you', $body, 'customer_team');
    }

    public static function processing(string $pipeline, string $outcome, int $organizationId, int $loanFileId, ?int $userId, string $runId): void
    {
        try {
            if (! self::enabled('customer_processing') || ! $userId) {
                return;
            }
            $user = User::find($userId);
            $file = LoanFile::find($loanFileId);
            if (! $user?->email || ! $user->email_verified_at || ! $file || ! Gate::forUser($user)->allows('review', $file)) {
                return;
            }
            $key = 'processing:'.$pipeline.':'.$runId.':'.$outcome;
            if (! self::claim($key, 'processing', $organizationId)) {
                return;
            }
            $label = $pipeline === 'bank' ? 'bank statement analysis' : 'appraisal and rental income review';
            if ($outcome === 'failed') {
                $body = "We could not finish the {$label} on a loan file.\n\nOpen the file to see the status and try again, or contact support if it keeps failing. This email does not include document contents or financial figures.\n\n".self::url('/files/'.$loanFileId);
                self::send($user->email, 'Non-QM Doc AI could not finish a file', $body, 'customer_processing');

                return;
            }
            $body = "Your {$label} is ready to review.\n\nOpen the loan file to see the results. This email does not include document contents or financial figures.\n\n".self::url('/files/'.$loanFileId);
            self::send($user->email, 'Your Non-QM Doc AI file is ready to review', $body, 'customer_processing');
        } catch (\Throwable $e) {
            Log::warning('Processing email was not sent', ['exception_type' => $e::class]);
        }
    }

    public static function credits(PageCreditAccount $account): void
    {
        try {
            $balance = (int) $account->balance;
            $used = (int) $account->used_this_period;
            $exhausted = $balance <= 0;
            $low = ! $exhausted && self::isLow($balance, $used);
            if (! $exhausted && ! $low) {
                return;
            }
            $kind = $exhausted ? 'exhausted' : 'low';
            $period = $account->period_starts_at?->format('Y-m-d') ?? 'open';
            if (! self::claim('credits-'.$kind.':'.$account->organization_id.':'.$period, 'credits-'.$kind, (int) $account->organization_id)) {
                return;
            }
            $org = Organization::find($account->organization_id);
            $name = $org->name ?? 'your workspace';
            $plans = self::url('/settings/plans');
            if ($exhausted) {
                $customer = "Page credits for {$name} are used up, so processing will stop until more pages are added.\n\nRequest a page pack or a plan change. Nothing is charged until the team confirms payment offline.\n\n{$plans}";
                $admin = "Page credits are used up for {$name}.\n\nBalance: 0 pages.\n\n".self::url('/admin/organizations/'.$account->organization_id);
            } else {
                $customer = "Page credits for {$name} are running low. {$balance} pages remain.\n\nRequest more pages before processing stops. Nothing is charged from this email.\n\n{$plans}";
                $admin = "Page credits are low for {$name}.\n\nPages remaining: {$balance}.\n\n".self::url('/admin/organizations/'.$account->organization_id);
            }
            $subject = $exhausted ? 'Non-QM Doc AI page credits are used up' : 'Non-QM Doc AI page credits are running low';
            self::sendMany(self::managers((int) $account->organization_id), $subject, $customer, 'customer_billing');
            self::sendMany(self::adminRecipients(), 'Non-QM Doc AI: '.$name.' page credits '.($exhausted ? 'used up' : 'low'), $admin, 'admin_credits');
        } catch (\Throwable $e) {
            Log::warning('Credit email was not sent', ['exception_type' => $e::class]);
        }
    }

    public static function isLow(int $balance, int $used): bool
    {
        if ($balance <= 0) {
            return false;
        }
        $floor = (int) config('notifications.low_pages', 50);
        $ratio = (float) config('notifications.low_ratio', 0.10);
        $total = $used + $balance;

        return $balance < $floor || ($total > 0 && ($balance / $total) < $ratio);
    }

    public static function billingReminders(): void
    {
        $days = max(1, (int) config('notifications.trial_notice_days', 3));
        $until = now()->addDays($days)->endOfDay();
        Organization::query()
            ->whereNull('suspended_at')
            ->where('subscription_status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now()->startOfDay(), $until])
            ->orderBy('id')
            ->each(function (Organization $org) {
                $stamp = $org->trial_ends_at?->toDateString();
                if (! $stamp || ! self::claim('trial-ending:'.$org->id.':'.$stamp, 'trial-ending', $org->id)) {
                    return;
                }
                $date = $org->trial_ends_at->timezone(config('app.timezone'))->format('F j, Y');
                $body = "The trial for {$org->name} ends on {$date}.\n\nSign in to review the plan or request a change. Nothing is charged automatically.\n\n".self::url('/settings/plans');
                self::sendMany(self::managers($org->id), 'Your Non-QM Doc AI trial is ending', $body, 'customer_billing');
            });

        PageCreditAccount::query()
            ->whereNotNull('period_ends_at')
            ->whereBetween('period_ends_at', [now()->startOfDay(), $until])
            ->orderBy('id')
            ->each(function (PageCreditAccount $account) {
                $org = Organization::find($account->organization_id);
                if (! $org || $org->suspended_at) {
                    return;
                }
                $stamp = $account->period_ends_at?->toDateString();
                if (! $stamp || ! self::claim('credit-period:'.$org->id.':'.$stamp, 'credit-period', $org->id)) {
                    return;
                }
                $date = $account->period_ends_at->timezone(config('app.timezone'))->format('F j, Y');
                $body = "The current page-credit period for {$org->name} ends on {$date}.\n\nUnused pages stay on your balance, and a new monthly allocation is added when the period rolls over.\n\n".self::url('/settings/plans');
                self::sendMany(self::managers($org->id), 'Your Non-QM Doc AI page-credit period is ending', $body, 'customer_billing');
            });
    }

    public static function claim(string $key, string $kind, ?int $organizationId = null): bool
    {
        try {
            // A savepoint keeps a duplicate claim from aborting the caller's transaction.
            DB::transaction(function () use ($key, $kind, $organizationId) {
                DB::table('outbound_notification_claims')->insert([
                    'claim_key' => mb_substr($key, 0, 191),
                    'kind' => mb_substr($kind, 0, 80),
                    'organization_id' => $organizationId,
                    'claimed_at' => now(),
                ]);
            });

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        } catch (\Throwable $e) {
            Log::warning('Notification claim failed', ['exception_type' => $e::class]);

            return false;
        }
    }
}
