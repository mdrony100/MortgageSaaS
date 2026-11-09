<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Ends existing sessions of users whose sign-in has been disabled by platform staff. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && $user->isDisabled()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'This account has been disabled. Contact your administrator.', 'code' => 'account_disabled'], 401);
            }

            return redirect('/login?disabled=1');
        }

        return $next($request);
    }
}
