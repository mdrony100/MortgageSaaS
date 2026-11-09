<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    public const STATUSES = ['trial' => 'Trial', 'active' => 'Active', 'past_due' => 'Past due', 'suspended' => 'Suspended', 'cancelled' => 'Cancelled'];

    protected $table = 'organizations';

    protected $guarded = [];

    protected $hidden = ['suspension_reason', 'approval_reason'];

    protected function casts(): array
    {
        return ['branding' => 'array', 'preferences' => 'array', 'suspended_at' => 'datetime', 'trial_ends_at' => 'datetime', 'approval_decided_at' => 'datetime'];
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function adminNotes()
    {
        return $this->hasMany(OrganizationAdminNote::class)->orderByDesc('id');
    }

    /** Members of a suspended or not-yet-approved organization cannot use the customer workspace. */
    public function blockedReason(): ?string
    {
        if ($this->approval_status === 'pending') {
            return 'organization_pending';
        }
        if ($this->approval_status === 'rejected') {
            return 'organization_rejected';
        }

        return $this->suspended_at ? 'organization_suspended' : null;
    }

    public function fileLimit(): int
    {
        return $this->file_limit_override ?? $this->plan->file_limit;
    }

    public function memberLimit(): int
    {
        return $this->member_limit_override ?? $this->plan->member_limit;
    }

    public function storageLimitBytes(): int
    {
        return (int) ($this->storage_limit_bytes_override ?? $this->plan->storage_limit_bytes);
    }
}
