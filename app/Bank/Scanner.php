<?php

namespace App\Bank;

use Symfony\Component\Process\Process;

class Scanner
{
    public function scan(string $path, bool $fictional): array
    {
        if ($fictional && app()->environment(['local', 'testing']) && config('bank.fictional_scan_bypass')) {
            return ['status' => 'Clean', 'adapter' => 'fictional-development-bypass', 'fictional_only' => true];
        }
        try {
            $p = new Process([config('bank.clamav'), '--no-summary', '--stdout', $path]);
            $p->setTimeout(45);
            $p->run();

            return ['status' => match ($p->getExitCode()) {
                0 => 'Clean',1 => 'Rejected',126,127 => 'Unavailable',default => 'ScanFailed'
            }, 'adapter' => 'clamav'];
        } catch (\Throwable) {
            return ['status' => 'Unavailable', 'adapter' => 'clamav'];
        }
    }
}
