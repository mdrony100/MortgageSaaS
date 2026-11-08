<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return [];
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version');
    }

    public function loanFile()
    {
        return $this->belongsTo(LoanFile::class);
    }
}
