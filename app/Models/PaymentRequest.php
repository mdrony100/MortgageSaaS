<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    public const KINDS = ['plan' => 'Plan change', 'pack' => 'Page top-up'];

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'decided_at' => 'datetime', 'paid_offline_at' => 'datetime'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class)->select(['id', 'name', 'plan_id']);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by')->select(['id', 'name', 'email']);
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by')->select(['id', 'name']);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class)->select(['id', 'name']);
    }
}
