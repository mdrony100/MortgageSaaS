<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';

    protected $guarded = [];

    protected $hidden = ['provisional_monthly_price'];

    protected function casts(): array
    {
        return ['provisional_monthly_price' => 'decimal:2'];
    }
}
