<?php

namespace App\Services;

class Providers
{
    public function states(): array
    {
        return ['extraction' => 'not_configured', 'openai' => 'not_configured', 'fraud' => 'not_configured — evaluate Ocrolus Detect / MoneyThumb Thumbprint in Phase 2', 'malware' => 'not_configured — never marked clean', 'billing' => 'disabled', 'plaid' => 'disabled', 'storage' => config('mortgage.private_disk', 'private'), 'email' => config('mail.default')];
    }
}
