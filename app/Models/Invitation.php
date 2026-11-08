<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $table = 'invitations';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['org_wide_access' => 'boolean', 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public $incrementing = false;

    protected $keyType = 'string';
}
