<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DocumentVersion extends Model
{
    protected $table = 'document_versions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [];
    }

    protected $appends = ['latest_scan_status'];

    public function getLatestScanStatusAttribute(): string
    {
        return DB::table('bank_scans')->where('document_version_id', $this->id)->where('organization_id', $this->organization_id)->latest('id')->value('status') ?? $this->scan_status;
    }

    protected $hidden = ['disk', 'storage_key'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Original versions are immutable.'));
        static::deleting(fn () => throw new \LogicException('Original versions cannot be deleted.'));
    }
}
