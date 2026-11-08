<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisRun extends Model
{
    protected $table = 'analysis_runs';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean', 'completed_at' => 'datetime'];
    }

    public $incrementing = false;

    protected $keyType = 'string';
}
