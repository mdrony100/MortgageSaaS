<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpersonationSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'expires_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id')->select(['id', 'name', 'email']);
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_user_id')->select(['id', 'name', 'email']);
    }
}
