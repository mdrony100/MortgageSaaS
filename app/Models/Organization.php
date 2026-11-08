<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $table = 'organizations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['branding' => 'array', 'preferences' => 'array'];
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
