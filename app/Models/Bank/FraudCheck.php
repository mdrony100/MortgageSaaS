<?php

namespace App\Models\Bank;

use Illuminate\Database\Eloquent\Model;

class FraudCheck extends Model
{
    protected $table = 'bank_fraud_checks';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'findings' => 'array',
            'signals' => 'array',
            'score' => 'integer',
        ];
    }
}
