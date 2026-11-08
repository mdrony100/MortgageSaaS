<?php

namespace App\Models\Bank;

use Illuminate\Database\Eloquent\Model;

class Run extends Model
{
    protected $table = 'bank_runs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'cancel_requested' => 'boolean', 'completed_at' => 'datetime'];
    }
}
