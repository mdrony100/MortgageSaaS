<?php

namespace App\Services;

use App\Models\AuditEvent;

class Audit
{
    public static function record(string $action, ?int $org = null, ?int $file = null, array $metadata = [], string $visibility = 'internal'): void
    {
        AuditEvent::create(['organization_id' => $org, 'loan_file_id' => $file, 'actor_id' => auth()->id(), 'action' => $action, 'metadata' => $metadata, 'visibility' => $visibility]);
    }
}
