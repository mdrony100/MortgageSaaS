<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for every /admin page and /api/admin endpoint: signed in, verified, platform_admin,
 * not disabled (EnsureAccountActive), not inside a support session, and confirmed 2FA when required.
 * Organization roles (owner/admin/...) never grant access.
 */
class OwnerConsole
{
    public function handle(Request $request, Closure $next, string $mode = 'api'): Response
    {
        $api = $mode === 'api';
        $user = $request->user();
        if (! $user) {
            return $api ? response()->json(['message' => 'Unauthenticated.'], 401) : redirect('/login?redirect='.urlencode($request->getRequestUri()));
        }
        if ($request->hasSession() && $request->session()->has('impersonation')) {
            return $this->deny($api, 'impersonating', 'End the support session before using the owner console.');
        }
        if (! $user->hasVerifiedEmail()) {
            return $api ? response()->json(['message' => 'Verify your email address first.', 'code' => 'email_unverified'], 403) : redirect('/verify-email');
        }
        if ($user->platform_admin !== true) {
            return $this->deny($api, 'not_platform_admin', 'The owner console is only available to MortgageLens platform staff.');
        }
        if (config('platform.console_require_2fa') && ! $user->hasConfirmedTwoFactor()) {
            return $this->deny($api, 'two_factor_required', 'Turn on two-factor sign-in in My account before opening the owner console.');
        }

        return $next($request);
    }

    private function deny(bool $api, string $code, string $message): Response
    {
        return $api ? response()->json(['message' => $message, 'code' => $code], 403) : response()->view('admin.denied', ['code' => $code, 'message' => $message], 403);
    }
}
