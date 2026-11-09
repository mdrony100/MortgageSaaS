<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationSession;
use App\Models\User;
use App\Services\Audit;
use App\Services\Impersonation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/**
 * Account administration without password access: no viewing/setting passwords, no granting
 * platform_admin (CLI only: mortgage:platform-admin). Support sessions are separately safeguarded.
 */
class UserAdminController extends Controller
{
    public function index(Request $r)
    {
        $f = $r->validate(['q' => 'nullable|string|max:120', 'filter' => ['nullable', Rule::in(['disabled', 'unverified', 'platform_admins', 'no_2fa'])], 'page' => 'nullable|integer|min:1']);
        $q = User::query()->with(['memberships' => fn ($m) => $m->select(['id', 'user_id', 'organization_id', 'role'])->with('organization:id,name')])
            ->addSelect(['users.*', 'last_login_audit' => DB::table('audit_events')->selectRaw('max(created_at)')->whereColumn('actor_id', 'users.id')->where('action', 'auth.login')])
            ->latest('id');
        if (! empty($f['q'])) {
            $term = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w->where('name', 'ilike', $term)->orWhere('email', 'ilike', $term));
        }
        match ($f['filter'] ?? null) {
            'disabled' => $q->whereNotNull('disabled_at'),
            'unverified' => $q->whereNull('email_verified_at'),
            'platform_admins' => $q->where('platform_admin', true),
            'no_2fa' => $q->whereNull('two_factor_confirmed_at'),
            default => null,
        };
        $page = $q->paginate(25);
        $page->getCollection()->transform(fn (User $u) => $this->row($u));

        return ['users' => $page, 'current_user_id' => $r->user()->id, 'impersonation' => ['enabled' => (bool) config('platform.impersonation.enabled'), 'allowed' => (bool) config('platform.impersonation.enabled') && $r->user()->hasConfirmedTwoFactor(), 'minutes' => (int) config('platform.impersonation.minutes')]];
    }

    private function row(User $u): array
    {
        $last = $u->last_login_at ?? ($u->last_login_audit ? Carbon::parse($u->last_login_audit) : null);

        return ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'verified' => $u->email_verified_at !== null, 'two_factor' => $u->hasConfirmedTwoFactor(), 'platform_admin' => (bool) $u->platform_admin, 'disabled_at' => $u->disabled_at, 'last_login_at' => $last, 'created_at' => $u->created_at, 'organizations' => $u->memberships->map(fn ($m) => ['id' => $m->organization_id, 'name' => $m->organization?->name, 'role' => $m->role])->values()];
    }

    public function action(Request $r, int $id)
    {
        $d = $r->validate(['action' => ['required', Rule::in(['disable', 'enable', 'resend_verification', 'send_password_reset'])], 'reason' => 'nullable|string|max:500']);
        $user = User::findOrFail($id);
        $admin = $r->user();
        $meta = ['target_user_id' => $user->id, 'platform_admin_id' => $admin->id];
        switch ($d['action']) {
            case 'disable':
                abort_if($user->id === $admin->id, 422, 'You cannot disable your own account.');
                abort_if($user->platform_admin, 422, 'Platform admins cannot be disabled here. Revoke platform admin with the mortgage:platform-admin command first.');
                $user->forceFill(['disabled_at' => now()])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                Audit::record('admin.user.disabled', null, null, $meta + ['reason' => $d['reason'] ?? null]);
                $message = 'Sign-in disabled. Existing sessions end on their next request.';
                break;
            case 'enable':
                $user->forceFill(['disabled_at' => null])->save();
                Audit::record('admin.user.enabled', null, null, $meta);
                $message = 'Sign-in enabled.';
                break;
            case 'resend_verification':
                abort_if($user->hasVerifiedEmail(), 422, 'This email address is already verified.');
                $sent = $this->safely(fn () => $user->sendEmailVerificationNotification());
                Audit::record('admin.user.verification_resent', null, null, $meta + ['delivered_to_transport' => $sent]);
                $message = $sent ? 'Verification email sent.' : 'The request was recorded but the email could not be handed to the mail transport. Check mail settings.';
                break;
            default:
                abort_if($user->isDisabled(), 422, 'Enable sign-in before sending a password reset link.');
                $status = null;
                $sent = $this->safely(function () use ($user, &$status) {
                    $status = Password::broker()->sendResetLink(['email' => $user->email]);
                });
                $ok = $sent && $status === Password::RESET_LINK_SENT;
                Audit::record('admin.user.password_reset_sent', null, null, $meta + ['delivered_to_transport' => $ok]);
                $message = $ok ? 'Password reset link sent. MortgageLens staff never see or set passwords.' : ($status === Password::RESET_THROTTLED ? 'A reset link was sent recently. Please wait before sending another.' : 'The reset link could not be sent. Check mail settings.');
        }
        $fresh = User::with(['memberships' => fn ($m) => $m->select(['id', 'user_id', 'organization_id', 'role'])->with('organization:id,name')])->findOrFail($user->id);

        return ['message' => $message, 'user' => $this->row($fresh)];
    }

    private function safely(callable $fn): bool
    {
        try {
            $fn();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Owner console email could not be sent', ['exception_type' => get_class($e)]);

            return false;
        }
    }

    public function impersonate(Request $r)
    {
        $d = $r->validate(['user_id' => 'required|integer', 'reason' => 'required|string|min:10|max:500']);
        $target = User::findOrFail($d['user_id']);
        $session = Impersonation::start($r, $r->user(), $target, trim($d['reason']));

        return ['redirect' => '/dashboard', 'expires_at' => $session->expires_at];
    }

    public function impersonations()
    {
        return ['sessions' => ImpersonationSession::with('admin', 'target')->latest('id')->limit(50)->get()];
    }
}
