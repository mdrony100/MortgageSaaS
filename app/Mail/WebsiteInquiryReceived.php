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
        $type = (string) config('website.inquiry_types.'.$this->inquiry->inquiry_type, 'Inquiry');
        $adminUrl = rtrim((string) config('app.url'), '/').'/admin/inquiries?id='.$this->inquiry->id;

        return new Content(
            html: 'mail.branded',
            text: 'mail.branded-text',
            with: BrandedMessage::data(
                'New website inquiry',
                ['A visitor sent a message from the Non-QM Doc AI website. The note they wrote is below.'],
                'Review inquiry',
                $adminUrl,
                [
                    ['label' => 'Type', 'value' => $type],
                    ['label' => 'Name', 'value' => (string) $this->inquiry->name],
                    ['label' => 'Work email', 'value' => (string) $this->inquiry->email],
                    ['label' => 'Company', 'value' => (string) $this->inquiry->company],
                    ['label' => 'Role', 'value' => $this->inquiry->role ?: 'Not provided'],
                    ['label' => 'Message', 'value' => (string) $this->inquiry->message],
                ],
                'You received this because website inquiries are sent to this address.',
                'New '.$type.' inquiry from the website.',
            ),
        );
    }
}
