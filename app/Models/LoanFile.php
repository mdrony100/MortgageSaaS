<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanFile extends Model
{
    protected $table = 'loan_files';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean', 'archived_at' => 'datetime', 'draft_inputs' => 'array'];
    }

    public function borrower()
    {
        return $this->belongsTo(Borrower::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function shares()
    {
        return $this->hasMany(FileShare::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
