<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $table = 'audit_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Audit is append-only.'));
        static::deleting(fn () => throw new \LogicException('Audit is append-only.'));
    }
}
