<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ReviewReady extends Mailable
{
    public function __construct(public string $reviewUrl) {}

    public function build()
    {
        return $this->subject('MortgageLens review processing update')->text('mail.review-ready');
    }
}
