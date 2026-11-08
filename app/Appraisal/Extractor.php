<?php

namespace App\Appraisal;

use App\Models\Review\Run;

interface Extractor
{
    public function extract(array $manifest, Run $run, int $version, string $directory): array;
}
