<?php

namespace App\Bank;

use App\Models\Bank\Run;

interface StatementExtractor
{
    public function extract(array $manifest, Run $run, int $versionId, string $directory): array;
}
