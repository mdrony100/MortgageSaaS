<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageCreditEntry extends Model
{
    public const TYPES = ['grant', 'monthly_allocation', 'top_up', 'consumption', 'adjustment'];

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime', 'pages' => 'integer', 'balance_after' => 'integer'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id')->select(['id', 'name']);
    }
}
