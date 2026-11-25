<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    public const STATUSES = ['new' => 'New', 'contacted' => 'Contacted', 'converted' => 'Converted', 'closed' => 'Closed'];

    protected $guarded = ['id'];

    protected $hidden = ['source_hash'];

    protected function casts(): array
    {
        return ['is_spam' => 'boolean', 'notified_at' => 'datetime'];
    }

    public function notes()
    {
        return $this->hasMany(DemoRequestNote::class)->orderBy('id');
    }
}
