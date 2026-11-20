<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Intentionally does not use LogsActivity so encrypted credentials never enter activity_log.
#[Fillable([
    'key',
    'value',
    'is_encrypted',
])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_encrypted' => 'boolean',
        ];
    }
}
