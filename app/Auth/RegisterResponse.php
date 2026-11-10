<?php

namespace App\Auth;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        $failed = (bool) ($request->user()->verificationEmailFailed ?? false);
        if ($request->wantsJson()) {
            return new JsonResponse([
                'verification_email_sent' => ! $failed,
                'message' => $failed
                    ? 'Your account was created, but the verification email could not be delivered. You are signed in. Use “Send verification link” on the next screen, or ask an administrator to resend it.'
                    : null,
            ], 201);
        }

        return redirect()->intended(Fortify::redirects('register'));
    }
}
