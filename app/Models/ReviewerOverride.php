<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Phase 2/3 typed boundary; no real financial analysis is performed in Phase 1. */
class ReviewerOverride extends Model
{
    protected $table = 'reviewer_overrides';

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
