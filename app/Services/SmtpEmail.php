<?php

namespace App\Services;

use App\Contracts\EmailDelivery;
use App\Mail\BrandedMessage;
use Illuminate\Support\Facades\Mail;

class SmtpEmail implements EmailDelivery
{
    public function status(): string
    {
        return config('mail.default') === 'smtp' ? 'configured_smtp' : 'local_transport';
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        Mail::to($recipient)->send(BrandedMessage::fromPlain($subject, $body));
    }
}
