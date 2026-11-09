<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteInquiry extends Model
{
    public const STATUSES = ['new' => 'New', 'in_progress' => 'In Progress', 'closed' => 'Closed'];

    protected $guarded = ['id'];

    protected $hidden = ['source_hash'];

    protected function casts(): array
    {
        return ['is_spam' => 'boolean', 'notified_at' => 'datetime', 'converted_at' => 'datetime'];
    }

    public function notes()
    {
        return $this->hasMany(WebsiteInquiryNote::class)->orderBy('id');
    }
}
