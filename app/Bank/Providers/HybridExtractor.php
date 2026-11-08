<?php

namespace App\Bank\Providers;

use App\Bank\ProcessingException;
use App\Bank\StatementExtractor;
use App\Models\Bank\Run;

class HybridExtractor implements StatementExtractor
{
    public function extract(array $manifest, Run $run, int $versionId, string $directory): array
    {
        $native = null;
        try {
            $native = (new NativeExtractor)->extract($manifest, $run, $versionId, $directory);
            if ($native['complete']) {
                return $native;
            }
        } catch (ProcessingException) {
        }
        $cfg = config('bank.openai');
        if ($cfg['approved'] && $cfg['key'] && $cfg['model']) {
            return (new OpenAIExtractor)->extract($manifest, $run, $versionId, $directory);
        }
        // No hidden provider call or fictional interpretation. Incomplete native evidence remains reviewable.
        if ($native !== null) {
            return $native;
        }
        throw new ProcessingException('Native layout unsupported; approved OpenAI vision extraction is unavailable');
    }
}
