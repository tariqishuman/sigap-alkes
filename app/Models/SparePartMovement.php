<?php

namespace App\Models;

use App\Enums\SparePartMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'spare_part_id',
    'type',
    'quantity',
    'repair_id',
    'user_id',
    'notes',
    'moved_at',
])]
class SparePartMovement extends Model
{
    protected function casts(): array
    {
        return [
            'type' => SparePartMovementType::class,
            'quantity' => 'integer',
            'moved_at' => 'datetime',
        ];
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
