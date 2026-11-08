<?php

namespace App\Contracts;

interface PlaidImport
{
    public function import(array $request): array;

    public function status(): string;
}
