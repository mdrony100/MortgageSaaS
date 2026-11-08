<?php

namespace App\Services;

use App\Contracts\DocumentClassification;
use App\Contracts\DocumentExtraction;
use App\Contracts\FraudDetection;
use App\Contracts\MalwareScanner;
use App\Contracts\OpenAIAnalysis;
use App\Contracts\PlaidImport;
use App\Contracts\SubscriptionBilling;

class DisabledProvider implements DocumentClassification, DocumentExtraction, FraudDetection, MalwareScanner, OpenAIAnalysis, PlaidImport, SubscriptionBilling
{
    public function status(): string
    {
        return 'not_configured';
    }

    private function disabled(): array
    {
        throw new \LogicException('Integration is disabled. No document processing was performed.');
    }

    public function extract(array $request): array
    {
        return $this->disabled();
    }

    public function analyze(array $request): array
    {
        return $this->disabled();
    }

    public function inspect(array $request): array
    {
        return $this->disabled();
    }

    public function checkout(array $request): array
    {
        return $this->disabled();
    }

    public function import(array $request): array
    {
        return $this->disabled();
    }

    public function classify(array $request): array
    {
        return $this->disabled();
    }

    public function scan(array $request): array
    {
        return ['status' => 'not_configured', 'clean' => null];
    }
}
