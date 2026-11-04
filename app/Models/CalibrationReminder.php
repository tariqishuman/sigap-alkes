<?php

namespace App\Models;

use App\Enums\ReminderKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['equipment_id', 'kind', 'cycle_valid_until', 'sent_at', 'channel'])]
class CalibrationReminder extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => ReminderKind::class,
            'cycle_valid_until' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
