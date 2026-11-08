<?php

namespace App\Models\Review;

use Illuminate\Database\Eloquent\Model;

class Run extends Model
{
    protected $table = 'review_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['inputs' => 'array', 'context' => 'array', 'checklist' => 'array', 'metrics' => 'array', 'cancel_requested' => 'boolean'];
    }
}
