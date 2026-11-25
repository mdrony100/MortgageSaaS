<?php

namespace App\Billing;

use App\Jobs\SendPlatformEmail;
use App\Models\DemoRequest;
use App\Models\Membership;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\NotificationMail;

/** Emails for offline payment requests and demo requests. Failures never undo the saved record. */
class CreditNotifier
{
    public static function paymentRequested(PaymentRequest $request): void
    {
        $request->loadMissing('organization', 'requester', 'plan');
        $what = $request->kind === 'plan'
            ? 'a plan change to '.($request->plan->name ?? 'another plan')
            : 'a top-up of '.number_format((int) $request->pack_pages).' pages';
        $body = "A customer requested {$what}.\n\nOrganization: {$request->organization->name}\nAmount shown: \${$request->amount} (placeholder, not charged)\nNote: ".($request->note ?: 'None')."\n\nReview it in the owner console: ".rtrim((string) config('app.url'), '/')."/admin/payment-requests\n";
        foreach (self::ownerAdmins() as $email) {
            SendPlatformEmail::safely($email, 'Non-QM Doc AI: new page or plan request', $body, 'admin_billing');
        }
        $requester = $request->requester?->email;
        if ($requester) {
            NotificationMail::paymentConfirmation($requester, $request->organization->name, $what, (string) $request->amount);
        }
    }

    public static function paymentDecided(PaymentRequest $request): void
    {
        $request->loadMissing('organization', 'requester');
        $email = $request->requester?->email;
        if (! $email) {
            $email = Membership::where('organization_id', $request->organization_id)->where('role', 'owner')->with('user:id,email')->get()->pluck('user.email')->filter()->first();
        }
        if (! $email) {
            return;
        }
        $outcome = match ($request->status) {
            'approved' => 'was approved. Payment was recorded as received offline, and the plan or pages have been applied.',
            'rejected' => 'was not approved.',
            default => 'was updated.',
        };
        $body = "Your request for {$request->organization->name} {$outcome}";
        if ($request->decision_reason) {
            $body .= "\n\nNote: {$request->decision_reason}";
        }
        $body .= "\n\nSign in to see your plan: ".rtrim((string) config('app.url'), '/')."/settings/plans\n";
        SendPlatformEmail::safely($email, 'Non-QM Doc AI: your request was '.$request->status, $body, 'customer_billing');
    }

    public static function demoReceived(DemoRequest $demo): void
    {
        $body = "A new demo request was submitted.\n\nName: {$demo->name}\nCompany: {$demo->company}\nEmail: {$demo->email}\nPhone: {$demo->phone}\nMonthly volume: {$demo->monthly_volume}\n\nReview it in the owner console: ".rtrim((string) config('app.url'), '/')."/admin/demo-requests\n";
        $sent = false;
        foreach (self::ownerAdmins() as $email) {
            $sent = SendPlatformEmail::safely($email, 'Non-QM Doc AI: new demo request', $body, 'admin_website') || $sent;
        }
        NotificationMail::demoConfirmation($demo->email, $demo->name, $demo->company);
        $demo->update([
            'notification_status' => $sent ? 'queued' : (self::ownerAdmins() === [] ? 'not_configured' : 'dispatch_failed'),
            'notified_at' => $sent ? now() : null,
        ]);
    }

    /** @return list<string> */
    public static function ownerAdmins(): array
    {
        $emails = User::query()->where('platform_admin', true)->whereNotNull('email')->pluck('email');
        $inbox = config('website.inquiry_notify_email');
        if (filled($inbox)) {
            $emails->push($inbox);
        }

        return $emails->map(fn ($email) => mb_strtolower((string) $email))->unique()->values()->all();
    }
}
