<?php

namespace App\Billing;

use App\Bank\ProcessingException;
use App\Contracts\SubscriptionBilling;

class UnavailableProvider implements SubscriptionBilling
{
    public function status(): string
    {
        return 'disabled';
    }

    public function checkout(array $request): array
    {
        throw new ProcessingException('Approved live billing provider not selected');
    }
}
