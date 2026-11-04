<?php

namespace App\Models;

use App\Enums\CalibrationResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'equipment_id',
    'testing_institution_id',
    'recorded_by',
    'tested_at',
    'result',
    'certificate_number',
    'valid_until',
    'certificate_path',
])]
class Calibration extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'tested_at' => 'date',
            'valid_until' => 'date',
            'result' => CalibrationResult::class,
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function testingInstitution(): BelongsTo
    {
        return $this->belongsTo(TestingInstitution::class);
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
