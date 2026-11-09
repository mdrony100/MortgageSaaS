<?php

namespace App\Services;

use App\Models\AuditEvent;

class Audit
{
    public static function record(string $action, ?int $org = null, ?int $file = null, array $metadata = [], string $visibility = 'internal', ?int $actorId = null): void
    {
        $imp = request()->hasSession() ? request()->session()->get('impersonation') : null;
        if ($imp && ! isset($metadata['admin_id'])) {
            // Writes made during an owner support session always carry both identities.
            $metadata += ['impersonator_id' => $imp['admin_id'], 'impersonated_user_id' => $imp['target_id']];
        }
        AuditEvent::create(['organization_id' => $org, 'loan_file_id' => $file, 'actor_id' => $actorId ?? auth()->id(), 'action' => $action, 'metadata' => $metadata, 'visibility' => $visibility]);
    }
}
