<?php

namespace App\Http\Controllers\Admin;

use App\Auth\AuthenticateCredentials;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Dedicated owner-console sign-in. Uses the same credential check and login rate limiter
 * as the customer sign-in, then admits only platform admins. Everyone else stays signed out.
 */
class AdminLoginController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();
        if ($user && $user->platform_admin === true && ! $request->session()->has('impersonation')) {
            return redirect($this->destination($request));
        }

        return response()->view('admin.login', [
            'redirectTo' => $this->destination($request),
            'customerSession' => $user !== null && $user->platform_admin !== true,
            'disabled' => $request->query('disabled') === '1',
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'redirect' => ['nullable', 'string', 'max:500'],
        ]);

        $user = AuthenticateCredentials::resolve($request);
        if (! $user) {
            throw ValidationException::withMessages([
                Fortify::username() => [trans('auth.failed')],
            ]);
        }
        if ($user->platform_admin !== true) {
            throw ValidationException::withMessages([
                Fortify::username() => 'This sign-in is only for Non-QM Doc AI platform administrators. This account was not signed in.',
            ]);
        }

        if ($this->needsTwoFactor($user)) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);
            TwoFactorAuthenticationChallenged::dispatch($user);
            $to = '/two-factor-challenge?redirect='.urlencode($this->destination($request));

            return $request->expectsJson()
                ? response()->json(['two_factor' => true, 'redirect' => $to])
                : redirect($to);
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $to = $this->destination($request);

        return $request->expectsJson()
            ? response()->json(['redirect' => $to])
            : redirect($to);
    }

    private function needsTwoFactor(User $user): bool
    {
        if (! in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true)) {
            return false;
        }
        if (Fortify::confirmsTwoFactorAuthentication()) {
            return $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;
        }

        return $user->two_factor_secret !== null;
    }

    private function destination(Request $request): string
    {
        foreach ([(string) $request->input('redirect', ''), (string) $request->query('redirect', '')] as $candidate) {
            if ($safe = $this->safeAdminPath($candidate)) {
                return $safe;
            }
        }
        $intended = (string) $request->session()->get('url.intended', '');
        if ($intended !== '') {
            $path = (string) (parse_url($intended, PHP_URL_PATH) ?: '');
            $query = parse_url($intended, PHP_URL_QUERY);
            if ($safe = $this->safeAdminPath($path.($query ? '?'.$query : ''))) {
                return $safe;
            }
        }

        return '/admin';
    }

    private function safeAdminPath(string $target): ?string
    {
        $target = trim($target);
        if ($target === '' || str_contains($target, '\\') || str_contains($target, '://') || str_starts_with($target, '//') || str_contains($target, '..')) {
            return null;
        }
        $parts = explode('?', $target, 2);
        $path = $parts[0];
        if (! preg_match('#^/admin(?:/[A-Za-z0-9._~!$&\'()*+,;=:@%-]*)?$#', $path)) {
            return null;
        }
        if ($path === '/admin/login' || str_starts_with($path, '/admin/login/')) {
            return null;
        }
        if (isset($parts[1]) && ! preg_match('/^[A-Za-z0-9._~%=&+-]*$/', $parts[1])) {
            return null;
        }

        return $target;
    }
}
