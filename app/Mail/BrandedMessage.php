<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** HTML + plain-text message on the shared Non-QM Doc AI layout. Sent by a queued job. */
class BrandedMessage extends Mailable
{
    /**
     * @param  list<string>  $lines
     * @param  list<array{label: string, value: string}>  $details
     */
    public function __construct(
        public string $emailSubject,
        public string $heading,
        public array $lines = [],
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public array $details = [],
        public ?string $reason = null,
        public ?string $preheader = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.branded',
            text: 'mail.branded-text',
            with: $this->viewData(),
        );
    }

    /** @return array<string, mixed> */
    public function viewData(): array
    {
        return self::data(
            $this->heading,
            $this->lines,
            $this->actionLabel,
            $this->actionUrl,
            $this->details,
            $this->reason,
            $this->preheader,
        );
    }

    /**
     * @param  list<string>  $lines
     * @param  list<array{label: string, value: string}>  $details
     * @return array<string, mixed>
     */
    public static function data(
        string $heading,
        array $lines = [],
        ?string $actionLabel = null,
        ?string $actionUrl = null,
        array $details = [],
        ?string $reason = null,
        ?string $preheader = null,
    ): array {
        return [
            'emailHeading' => $heading,
            'emailLines' => array_values(array_filter($lines, fn ($line) => trim((string) $line) !== '')),
            'emailActionLabel' => $actionLabel,
            'emailActionUrl' => $actionUrl,
            'emailDetails' => $details,
            'emailReason' => $reason ?: 'You received this email because of activity on your Non-QM Doc AI account.',
            'emailPreheader' => $preheader ?: ($lines[0] ?? $heading),
            'emailLogoUrl' => (string) config('notifications.logo_url'),
            'emailSupport' => (string) config('notifications.support_email'),
            'emailCompany' => (string) config('notifications.company'),
        ];
    }

    public static function fromPlain(string $subject, string $body): self
    {
        $actionUrl = null;
        $lines = [];
        foreach (preg_split("/\r\n|\n|\r/", trim($body)) ?: [] as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            if (preg_match('~https?://\S+~', $trim, $match)) {
                $url = rtrim($match[0], '.,);>');
                $actionUrl ??= $url;
                if ($trim === $url || $trim === $match[0]) {
                    continue;
                }
            }
            $lines[] = $trim;
        }
        $heading = trim((string) preg_replace('/^Non-QM Doc AI:\s*/i', '', $subject));
        if ($heading === '') {
            $heading = 'Non-QM Doc AI';
        }

        return new self(
            emailSubject: $subject,
            heading: $heading,
            lines: $lines === [] ? ['There is an update in your Non-QM Doc AI workspace.'] : $lines,
            actionLabel: self::actionLabel($actionUrl),
            actionUrl: $actionUrl,
            reason: self::reasonFor($subject.' '.$body),
            preheader: $lines[0] ?? $heading,
        );
    }

    private static function actionLabel(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);

        return match (true) {
            str_contains($path, '/invitations') => 'Accept invitation',
            str_contains($path, '/admin/payment-requests') => 'Review payment request',
            str_contains($path, '/admin/demo-requests') => 'Review demo request',
            str_contains($path, '/admin/support') => 'Open support tickets',
            str_contains($path, '/admin/approvals') => 'Review request',
            str_contains($path, '/admin/inquiries') => 'Review inquiry',
            str_contains($path, '/admin') => 'Open the owner console',
            str_contains($path, '/settings/plans') => 'View your plan',
            str_contains($path, '/support') => 'Open the ticket',
            str_contains($path, '/files/') => 'Open the loan file',
            str_contains($path, '/reset-password') => 'Choose a new password',
            str_contains($path, '/login'), str_contains($path, '/dashboard') => 'Sign in',
            default => 'Open Non-QM Doc AI',
        };
    }

    private static function reasonFor(string $text): string
    {
        $haystack = mb_strtolower($text);

        return match (true) {
            str_contains($haystack, 'invitation') => 'You received this because someone invited you to a Non-QM Doc AI workspace.',
            str_contains($haystack, 'demo request') => 'You received this because a demo was requested from the Non-QM Doc AI website.',
            str_contains($haystack, 'support ticket') => 'You received this because of a support ticket on your Non-QM Doc AI workspace.',
            str_contains($haystack, 'page credit'), str_contains($haystack, 'page or plan'), str_contains($haystack, 'your request') => 'You received this because of a plan or page-credit request on your workspace.',
            str_contains($haystack, 'signed up'), str_contains($haystack, 'welcome') => 'You received this because a Non-QM Doc AI account was created for this email address.',
            str_contains($haystack, 'trial'), str_contains($haystack, 'page-credit period') => 'You received this because a plan or page-credit period on your workspace is ending.',
            str_contains($haystack, 'shared a loan'), str_contains($haystack, 'loan file') => 'You received this because of a loan file in your Non-QM Doc AI workspace.',
            default => 'You received this email because of activity on your Non-QM Doc AI account.',
        };
    }
}
