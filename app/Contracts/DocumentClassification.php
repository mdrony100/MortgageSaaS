<?php

namespace App\Contracts;

interface DocumentClassification
{
    public function classify(array $request): array;

    public function status(): string;
}
