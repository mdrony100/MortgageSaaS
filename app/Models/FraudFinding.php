<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Phase 2/3 typed boundary; no real financial analysis is performed in Phase 1. */
class FraudFinding extends Model
{
    protected $table = 'fraud_findings';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['data' => 'array', 'amount' => 'decimal:2', 'is_demo' => 'boolean'];
    }

    public function loanFile()
    {
        return $this->belongsTo(LoanFile::class);
    }
}
