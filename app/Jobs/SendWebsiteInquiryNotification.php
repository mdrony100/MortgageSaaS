<?php

namespace App\Jobs;

use App\Mail\WebsiteInquiryReceived;
use App\Models\WebsiteInquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWebsiteInquiryNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $inquiryId) {}

    public function handle(): void
    {
        $inquiry = WebsiteInquiry::find($this->inquiryId);
        if (! $inquiry || $inquiry->is_spam || $inquiry->notification_status === 'sent') {
            return;
        }
        $to = config('website.inquiry_notify_email');
        if (! $to) {
            $inquiry->update(['notification_status' => 'not_configured']);

            return;
        }
        Mail::to($to)->send(new WebsiteInquiryReceived($inquiry));
        $inquiry->update(['notification_status' => 'sent', 'notification_error' => null, 'notified_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Website inquiry notification failed', ['inquiry_id' => $this->inquiryId, 'exception_type' => get_class($e)]);
        WebsiteInquiry::whereKey($this->inquiryId)->update(['notification_status' => 'failed', 'notification_error' => class_basename($e)]);
    }
}
