<?php

namespace App\Bank;

use Symfony\Component\Process\Process;

class Scanner
{
    public function installed(): bool
    {
        $bin = (string) config('bank.clamav', 'clamscan');
        if ($bin === '') {
            return false;
        }
        if (str_contains($bin, DIRECTORY_SEPARATOR)) {
            return is_executable($bin);
        }
        foreach (explode(':', (string) getenv('PATH')) as $dir) {
            if ($dir !== '' && is_executable($dir.DIRECTORY_SEPARATOR.$bin)) {
                return true;
            }
        }

        return false;
    }

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
                0 => 'Clean',1 => 'Rejected',126,127 => 'Unscanned',default => 'ScanFailed'
            }, 'adapter' => 'clamav', 'clean' => $p->getExitCode() === 0];
        } catch (\Throwable) {
            // Missing binary is not a clean result. Shared hosts without ClamAV keep the original and mark it unscanned.
            return ['status' => 'Unscanned', 'adapter' => 'clamav', 'clean' => false, 'reason' => 'Scanner unavailable; original is not certified clean'];
        }
    }
}
