<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteInquiryNote extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->select(['id', 'name']);
    }
}
