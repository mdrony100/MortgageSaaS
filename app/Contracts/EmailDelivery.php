<?php

namespace App\Contracts;

interface EmailDelivery
{
    public function send(string $recipient, string $subject, string $body): void;

    public function status(): string;
}
