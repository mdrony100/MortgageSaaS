<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'secret_value', 'secret', 'updated_by'];

    protected $hidden = ['value', 'secret_value'];

    protected function casts(): array
    {
        return ['secret_value' => 'encrypted', 'secret' => 'boolean'];
    }
}
