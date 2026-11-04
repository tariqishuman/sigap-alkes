<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use App\Services\DepreciationCalculator;
use App\Services\EquipmentStatusService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'inventory_code',
    'name',
    'equipment_type_id',
    'room_id',
    'brand',
    'model',
    'serial_number',
    'acquisition_date',
    'acquisition_cost',
    'residual_value',
    'qr_token',
    'photo_path',
    'is_active',
    'notes',
])]
class Equipment extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'equipment';

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'status' => EquipmentStatus::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Equipment $equipment) {
            if (empty($equipment->qr_token)) {
                $equipment->qr_token = (string) Str::uuid();
            }

            if (empty($equipment->status)) {
                $equipment->loadMissing('equipmentType');
                $equipment->status = $equipment->equipmentType?->requires_calibration
                    ? EquipmentStatus::CalibrationDue
                    : EquipmentStatus::FitForUse;
            }
        });

        static::created(function (Equipment $equipment) {
            app(EquipmentStatusService::class)->initializeStatus($equipment);
        });
    }

    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class)->orderByDesc('changed_at');
    }

    public function getBookValueAttribute(): string
    {
        return app(DepreciationCalculator::class)->calculateForEquipment($this)['book_value'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
