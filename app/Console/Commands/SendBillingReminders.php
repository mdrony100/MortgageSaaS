<?php

namespace App\Console\Commands;

use App\Services\NotificationMail;
use Illuminate\Console\Command;

class SendBillingReminders extends Command
{
    protected $signature = 'notifications:billing-reminders';

    protected $description = 'Email organizations whose trial or page-credit period ends within the notice window. Each notice is sent once.';

    public function handle(): int
    {
        NotificationMail::billingReminders();
        $this->info('Billing reminders checked.');

        return self::SUCCESS;
    }
}
