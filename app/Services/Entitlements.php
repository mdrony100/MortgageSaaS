<?php

namespace App\Services;

use App\Models\DocumentVersion;
use App\Models\Invitation;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Validation\ValidationException;

class Entitlements
{
    public static function files(Organization $org): void
    {
        if (LoanFile::where('organization_id', $org->id)->count() >= $org->plan->file_limit) {
            throw ValidationException::withMessages(['plan' => 'File limit reached. Archive preserves originals and does not reset this limit.']);
        }
    }

    public static function members(Organization $org): void
    {
        $count = Membership::where('organization_id', $org->id)->count() + Invitation::where('organization_id', $org->id)->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count();
        if ($count >= $org->plan->member_limit) {
            throw ValidationException::withMessages(['plan' => 'Member limit reached (including pending invitations).']);
        }
    }

    public static function storage(Organization $org, int $bytes): void
    {
        if (DocumentVersion::where('organization_id', $org->id)->sum('bytes') + $bytes > $org->plan->storage_limit_bytes) {
            throw ValidationException::withMessages(['plan' => 'Storage limit reached.']);
        }
    }
}
