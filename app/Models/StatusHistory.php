<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'equipment_id',
    'from_status',
    'to_status',
    'changed_at',
    'reason',
    'changed_by',
])]
class StatusHistory extends Model
{
    protected function casts(): array
    {
        return [
            'from_status' => EquipmentStatus::class,
            'to_status' => EquipmentStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
