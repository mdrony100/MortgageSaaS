<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageCreditAccount extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'used_this_period' => 'integer',
            'allocated_this_period' => 'integer',
            'trial_granted' => 'boolean',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
        ];
    }

    public function entries()
    {
        return $this->hasMany(PageCreditEntry::class, 'organization_id', 'organization_id');
    }
}
