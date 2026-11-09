<?php

namespace App\Mail;

use App\Models\WebsiteInquiry;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class WebsiteInquiryReceived extends Mailable
{
    public function __construct(public WebsiteInquiry $inquiry) {}

    public function envelope(): Envelope
    {
        // Subject never contains visitor-supplied text.
        return new Envelope(
            subject: 'New website inquiry: '.config('website.inquiry_types.'.$this->inquiry->inquiry_type, 'Inquiry'),
            replyTo: [new Address($this->inquiry->email, $this->inquiry->name)],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.website-inquiry', with: [
            'typeLabel' => config('website.inquiry_types.'.$this->inquiry->inquiry_type, 'Inquiry'),
            'adminUrl' => rtrim((string) config('app.url'), '/').'/platform/inquiries?id='.$this->inquiry->id,
        ]);
    }
}
