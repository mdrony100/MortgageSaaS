<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    protected $table = 'document_versions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [];
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
