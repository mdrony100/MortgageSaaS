<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model
{
    protected $table = 'support_ticket_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['staff' => 'boolean'];
    }

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachments()
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }
}
