<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisSnapshot extends Model
{
    protected $table = 'analysis_snapshots';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'is_demo' => 'boolean'];
    }
}
