<?php

namespace App\Http\Middleware;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LoanFile;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer workspace endpoints are unavailable while an organization is suspended,
 * pending owner approval, or rejected. The owner console is not affected.
 */
class BlockInactiveOrganization
{
    public const MESSAGES = [
        'organization_suspended' => 'This organization is suspended. Contact MortgageLens support to restore access.',
        'organization_pending' => 'This organization is waiting for approval. You will receive an email when it is approved.',
        'organization_rejected' => 'This organization was not approved. Contact MortgageLens support for details.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $orgId = $this->organizationId($request);
        if ($orgId) {
            $org = Organization::find($orgId);
            $reason = $org?->blockedReason();
            if ($reason) {
                return response()->json(['message' => self::MESSAGES[$reason], 'code' => $reason], 423);
            }
        }

        return $next($request);
    }

    private function organizationId(Request $request): ?int
    {
        $route = $request->route();
        if (! $route) {
            return null;
        }
        $p = $route->parameters();
        if (isset($p['organization'])) {
            return (int) ($p['organization'] instanceof Organization ? $p['organization']->id : $p['organization']);
        }
        $map = ['file' => LoanFile::class, 'document' => Document::class, 'version' => DocumentVersion::class];
        foreach ($map as $key => $class) {
            if (isset($p[$key])) {
                $model = $p[$key] instanceof $class ? $p[$key] : $class::find($p[$key]);

                return $model?->organization_id;
            }
        }
        if ($request->filled('organization_id')) {
            return (int) $request->input('organization_id');
        }

        return null;
    }
}
