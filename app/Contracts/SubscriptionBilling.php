<?php

namespace App\Contracts;

interface SubscriptionBilling
{
    public function checkout(array $request): array;

    public function status(): string;
}
