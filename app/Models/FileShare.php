<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileShare extends Model
{
    protected $table = 'file_shares';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }
}
