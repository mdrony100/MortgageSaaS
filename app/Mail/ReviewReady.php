<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReviewReady extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $reviewUrl) {}

    public function build()
    {
        $data = BrandedMessage::data(
            'Your review is ready',
            [
                'Your appraisal and rental income review is ready for a person to look at.',
                'This message has no document attachments and no financial details.',
            ],
            'Open the loan file',
            $this->reviewUrl,
            [],
            'You received this because you started an appraisal or rental income review.',
            'Your appraisal and rental income review is ready.',
        );

        return $this->subject('Non-QM Doc AI review processing update')->view('mail.branded', $data)->text('mail.branded-text', $data);
    }
}
