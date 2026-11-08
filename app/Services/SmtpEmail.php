<?php

namespace App\Services;

use App\Contracts\EmailDelivery;
use Illuminate\Support\Facades\Mail;

class SmtpEmail implements EmailDelivery
{
    public function status(): string
    {
        return config('mail.default') === 'smtp' ? 'configured_smtp' : 'local_transport';
    }

    public function send(string $recipient, string $subject, string $body): void
    {
        Mail::raw($body, fn ($m) => $m->to($recipient)->subject($subject));
    }
}
