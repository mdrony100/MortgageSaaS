<?php

namespace App\Services;

use App\Models\LoanFile;
use App\Models\Membership;
use App\Models\User;

class Access
{
    public static function membership(User $user, int $org): ?Membership
    {
        return Membership::where('user_id', $user->id)->where('organization_id', $org)->first();
    }

    public static function role(User $user, int $org): ?string
    {
        return self::membership($user, $org)?->role;
    }

    public static function admin(User $user, int $org): bool
    {
        return in_array(self::role($user, $org), ['owner', 'admin'], true);
    }

    public static function reviewer(User $user, int $org): bool
    {
        return in_array(self::role($user, $org), ['owner', 'admin', 'underwriter'], true);
    }

    public static function broker(User $user, int $org): bool
    {
        return self::role($user, $org) === 'broker';
    }

    public static function visible(User $user)
    {
        return LoanFile::query()->whereHas('organization', fn ($o) => $o->whereNull('suspended_at')->where('approval_status', 'approved'))->whereHas('organization.memberships', fn ($m) => $m->where('user_id', $user->id))->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)->orWhereHas('shares', fn ($s) => $s->where('user_id', $user->id)->whereNull('revoked_at'))->orWhereHas('organization.memberships', fn ($m) => $m->where('user_id', $user->id)->where('org_wide_access', true)->whereIn('role', ['owner', 'admin']));
        });
    }
}
