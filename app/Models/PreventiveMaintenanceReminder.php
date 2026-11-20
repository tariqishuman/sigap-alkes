<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'equipment_id',
    'kind',
    'cycle_due_at',
    'sent_at',
    'channel',
])]
class PreventiveMaintenanceReminder extends Model
{
    protected function casts(): array
    {
        return [
            'cycle_due_at' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
