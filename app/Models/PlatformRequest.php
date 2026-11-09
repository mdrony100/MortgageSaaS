<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Approval queue item: new organization signup or a customer plan-change request. */
class PlatformRequest extends Model
{
    public const TYPES = ['signup' => 'New organization signup', 'plan_change' => 'Plan change request'];

    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'decided_at' => 'datetime'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class)->select(['id', 'name', 'plan_id', 'subscription_status', 'approval_status']);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by')->select(['id', 'name', 'email']);
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by')->select(['id', 'name']);
    }
}
