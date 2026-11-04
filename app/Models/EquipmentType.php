<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'name',
    'requires_calibration',
    'requires_installation',
    'calibration_interval_months',
    'useful_life_years',
    'residual_value_percent',
])]
class EquipmentType extends Model
{
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'requires_calibration' => 'boolean',
            'requires_installation' => 'boolean',
            'calibration_interval_months' => 'integer',
            'useful_life_years' => 'integer',
            'residual_value_percent' => 'integer',
        ];
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
