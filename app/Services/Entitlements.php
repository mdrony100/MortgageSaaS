<?php

namespace App\Services;

use App\Models\DocumentVersion;
use App\Models\Invitation;
use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
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
        $reviewDerived = DB::table('review_records')->where('organization_id', $org->id)->whereIn('kind', ['page', 'image', 'report_artifact'])->selectRaw("COALESCE(SUM((data->>'artifact_bytes')::bigint),0) as bytes")->value('bytes');
        $derived = DB::table('bank_pages')->where('organization_id', $org->id)->selectRaw("COALESCE(SUM((data->>'artifact_bytes')::bigint),0) as bytes")->value('bytes');
        if (DocumentVersion::where('organization_id', $org->id)->sum('bytes') + (int) $derived + (int) $reviewDerived + $bytes > $org->plan->storage_limit_bytes) {
            throw ValidationException::withMessages(['plan' => 'Storage limit reached.']);
        }
    }
}
