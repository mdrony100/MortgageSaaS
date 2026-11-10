<?php

namespace App\Services;

use App\Models\ImpersonationSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Owner support sessions ("login as customer"). The admin identity is kept server-side in the
 * session only (never sent to the browser as a token), the session id is regenerated on start
 * and stop, and every start/stop/write is audited with both the admin and target user ids.
 */
class Impersonation
{
    /** Suppresses the normal sign-in bookkeeping while identities are being switched. */
    public static bool $switching = false;

    public static function current(?Request $request = null): ?array
    {
        $request ??= request();

        return $request->hasSession() ? $request->session()->get('impersonation') : null;
    }

    public static function start(Request $request, User $admin, User $target, string $reason): ImpersonationSession
    {
        abort_unless(config('platform.impersonation.enabled'), 403, 'Support sessions are turned off. Enable them in owner console Settings.');
        abort_unless($admin->platform_admin === true && $admin->hasConfirmedTwoFactor(), 403, 'Support sessions require a platform admin with confirmed two-factor sign-in.');
        abort_if(self::current($request) !== null, 409, 'A support session is already active.');
        if ($target->id === $admin->id || $target->platform_admin) {
            throw ValidationException::withMessages(['user_id' => 'Platform admins cannot be viewed through a support session.']);
        }
        if ($target->isDisabled()) {
            throw ValidationException::withMessages(['user_id' => 'Disabled users cannot be viewed through a support session.']);
        }
        $minutes = (int) config('platform.impersonation.minutes');
        $session = ImpersonationSession::create(['admin_id' => $admin->id, 'target_user_id' => $target->id, 'reason' => $reason, 'started_at' => now(), 'expires_at' => now()->addMinutes($minutes)]);
        $meta = ['admin_id' => $admin->id, 'target_user_id' => $target->id, 'support_session_id' => $session->id];
        Audit::record('impersonation.started', null, null, $meta + ['reason' => $reason, 'expires_at' => $session->expires_at->toIso8601String()], 'internal', $admin->id);
        foreach ($target->memberships()->pluck('organization_id') as $orgId) {
            Audit::record('platform_support.accessed', $orgId, null, $meta, 'internal', $admin->id);
        }

        self::$switching = true;
        try {
            $request->session()->put('impersonation', ['id' => $session->id, 'admin_id' => $admin->id, 'target_id' => $target->id, 'expires_at' => $session->expires_at->getTimestamp()]);
            $request->session()->forget(['password_hash_web', 'password_hash_sanctum', 'auth.password_confirmed_at']);
            Auth::guard('web')->login($target);
            $request->session()->regenerate();
            $request->session()->regenerateToken();
        } finally {
            self::$switching = false;
        }

        return $session;
    }

    /** Ends the support session and restores the admin if they are still an active platform admin. */
    public static function stop(Request $request, string $reason): bool
    {
        $imp = self::current($request);
        if (! $imp) {
            return false;
        }
        $session = ImpersonationSession::find($imp['id']);
        $session?->update(['ended_at' => now(), 'end_reason' => $reason]);
        $request->session()->forget(['impersonation', 'password_hash_web', 'password_hash_sanctum', 'auth.password_confirmed_at']);
        $admin = User::find($imp['admin_id']);
        self::$switching = true;
        try {
            if ($admin && $admin->platform_admin && ! $admin->isDisabled()) {
                Auth::guard('web')->login($admin);
                $request->session()->regenerate();
                $request->session()->regenerateToken();
                Audit::record('impersonation.ended', null, null, ['admin_id' => $imp['admin_id'], 'target_user_id' => $imp['target_id'], 'support_session_id' => $imp['id'], 'end_reason' => $reason], 'internal', (int) $imp['admin_id']);

                return true;
            }
            Audit::record('impersonation.ended', null, null, ['admin_id' => $imp['admin_id'], 'target_user_id' => $imp['target_id'], 'support_session_id' => $imp['id'], 'end_reason' => $reason, 'admin_restored' => false], 'internal', (int) $imp['admin_id']);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return false;
        } finally {
            self::$switching = false;
        }
    }
}
