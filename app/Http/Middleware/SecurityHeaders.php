<?php

namespace App\Http\Middleware;

use App\Website\Site;
use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $r, Closure $next)
    {
        $response = $next($r);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // Only explicitly indexable public marketing pages may be indexed; everything else
        // (auth, dashboard, API, previews/staging) is noindex. This is not an access control.
        $route = $r->route()?->getName();
        if (! (is_string($route) && str_starts_with($route, 'site.') && Site::indexable($route))) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if ($r->is('api/*')) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        }

        return $response;
    }
}
