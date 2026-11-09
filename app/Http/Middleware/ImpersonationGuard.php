<?php

namespace App\Http\Middleware;

use App\Services\Audit;
use App\Services\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces owner support session limits: expiry, kill switch, blocked sensitive actions,
 * and an audit entry (admin + target ids) for every write performed while viewing as a customer.
 */
class ImpersonationGuard
{
    /** [methods, path pattern] pairs that are never allowed during a support session. */
    private const BLOCKED = [
        [['*'], 'api/auth/user/*'],                       // password, email/profile, 2FA, recovery codes, password confirmation
        [['*'], 'api/auth/login'],
        [['*'], 'api/auth/register'],
        [['*'], 'api/auth/forgot-password'],
        [['*'], 'api/auth/reset-password'],
        [['*'], 'api/auth/two-factor-challenge'],
        [['*'], 'api/auth/email/*'],
        [['POST'], 'api/organizations/*/billing'],          // billing changes
        [['POST'], 'api/organizations/*/plan-requests'],
        [['POST'], 'api/organizations/*/invitations'],      // granting roles
        [['DELETE'], 'api/organizations/*/invitations/*'],
        [['PATCH', 'DELETE'], 'api/organizations/*/members/*'],
        [['POST'], 'api/invitations/accept'],
        [['POST'], 'api/files/*/appraisal/guidelines'],     // approving guidelines
        [['POST'], 'api/files/*/bank/rules'],
        [['*'], 'api/admin/*'],
        [['*'], 'admin'],
        [['*'], 'admin/*'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $imp = Impersonation::current($request);
        if (! $imp) {
            return $next($request);
        }
        if ($request->is('api/impersonation/stop')) {
            return $next($request);
        }
        $expired = now()->getTimestamp() >= (int) $imp['expires_at'];
        if ($expired || ! config('platform.impersonation.enabled') || (int) $request->user()?->id !== (int) $imp['target_id']) {
            Impersonation::stop($request, $expired ? 'expired' : 'invalidated');
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'The support session has ended.', 'code' => 'impersonation_ended', 'redirect' => '/admin/users'], 401);
            }

            return redirect('/admin/users');
        }
        if ($this->blocked($request)) {
            Audit::record('impersonation.blocked', null, null, ['admin_id' => $imp['admin_id'], 'target_user_id' => $imp['target_id'], 'method' => $request->method(), 'path' => $request->path()]);
            if ($request->is('admin', 'admin/*') && ! $request->expectsJson()) {
                return response()->view('admin.denied', ['code' => 'impersonating', 'message' => 'End the support session before using the owner console.'], 403);
            }

            return response()->json(['message' => 'This action is not available during an owner support session.', 'code' => 'impersonation_blocked'], 403);
        }
        $response = $next($request);
        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && ! $request->is('api/auth/logout') && $request->user()) {
            Audit::record('impersonation.write', null, null, ['admin_id' => $imp['admin_id'], 'target_user_id' => $imp['target_id'], 'method' => $request->method(), 'route' => $request->route()?->uri(), 'status' => $response->getStatusCode()]);
        }

        return $response;
    }

    private function blocked(Request $request): bool
    {
        foreach (self::BLOCKED as [$methods, $pattern]) {
            if (($methods === ['*'] || in_array($request->method(), $methods, true)) && $request->is($pattern)) {
                return true;
            }
        }

        // Original uploads and appraisal artifacts derived from them (page images/photos).
        return $request->isMethod('GET') && $request->is('api/document-versions/*/content', 'api/files/*/appraisal/runs/*/artifacts/*')
            && ! config('platform.impersonation.allow_document_download');
    }
}
