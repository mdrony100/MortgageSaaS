<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ruleset extends Model
{
    protected $table = 'rulesets';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['rules' => 'array'];
    }
}
