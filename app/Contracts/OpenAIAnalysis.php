<?php

namespace App\Contracts;

interface OpenAIAnalysis
{
    public function analyze(array $request): array;

    public function status(): string;
}
