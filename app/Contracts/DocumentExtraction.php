<?php

namespace App\Contracts;

interface DocumentExtraction
{
    public function extract(array $request): array;

    public function status(): string;
}
