<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

/**
 * Shared email/password check for the customer sign-in and the owner-console sign-in.
 * A match does not start a session. A disabled account is rejected before any login.
 */
class AuthenticateCredentials
{
    public static function resolve(Request $request): ?User
    {
        $user = User::where('email', Str::lower((string) $request->input(Fortify::username())))->first();
        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            return null;
        }
        if ($user->isDisabled()) {
            throw ValidationException::withMessages([
                Fortify::username() => 'This account has been disabled. Contact your administrator.',
            ]);
        }

        return $user;
    }
}
