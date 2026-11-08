<?php

namespace App\Contracts;

interface FraudDetection
{
    public function inspect(array $request): array;

    public function status(): string;
}
