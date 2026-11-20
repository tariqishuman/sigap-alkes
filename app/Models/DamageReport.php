<?php

namespace App\Models;

use App\Enums\DamageReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'equipment_id',
    'reported_by',
    'reported_at',
    'complaint',
    'status',
])]
class DamageReport extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'status' => DamageReportStatus::class,
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function repair(): HasOne
    {
        return $this->hasOne(Repair::class);
    }

    public function isOpen(): bool
    {
        return $this->status === DamageReportStatus::Open;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
