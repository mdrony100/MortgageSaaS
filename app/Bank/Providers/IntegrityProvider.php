<?php

namespace App\Bank\Providers;

use App\Models\Bank\Run;

interface IntegrityProvider
{
    public function analyze(Run $run, int $versionId, string $originalPath, string $sha256): array;
}
