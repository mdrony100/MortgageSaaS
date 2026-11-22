<?php

namespace App\Services;

class Providers
{
    public function states(): array
    {
        return ['extraction' => 'not_configured', 'openai' => 'not_configured', 'fraud' => (config('bank.fraud.engine') === 'openai' && filled(config('bank.openai.key')) && filled(config('bank.openai.model'))) ? 'openai' : 'not_configured', 'malware' => 'not_configured — never marked clean', 'billing' => 'disabled', 'plaid' => 'disabled', 'storage' => config('mortgage.private_disk', 'private'), 'email' => config('mail.default')];
    }
}
