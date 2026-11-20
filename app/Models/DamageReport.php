<?php

namespace App\Models;

use App\Enums\DamageReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'equipment_id',
    'reported_by',
    'reported_at',
    'complaint',
    'is_safe_to_use',
    'photos',
    'status',
    'marked_unfit_by_report',
    'verified_by',
    'verified_at',
    'rejected_reason',
    'closed_without_repair_reason',
    'closed_by',
    'closed_at',
    'acknowledged_by',
    'acknowledged_at',
    'escalated_at',
])]
class DamageReport extends Model
{
    use LogsActivity;

    public bool $wasMergedIntoActiveReport = false;

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'is_safe_to_use' => 'boolean',
            'photos' => 'array',
            'status' => DamageReportStatus::class,
            'marked_unfit_by_report' => 'boolean',
            'verified_at' => 'datetime',
            'closed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'escalated_at' => 'datetime',
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

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_by !== null;
    }

    public function repair(): HasOne
    {
        return $this->hasOne(Repair::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(DamageReportNote::class)->orderBy('reported_at')->orderBy('id');
    }

    public function isOpen(): bool
    {
        return $this->status === DamageReportStatus::Open;
    }

    public function isPendingVerification(): bool
    {
        return $this->status === DamageReportStatus::PendingVerification;
    }

    public function isActive(): bool
    {
        return $this->status?->isActive() ?? false;
    }

    public function canBeClosedWithoutRepair(): bool
    {
        return in_array($this->status, [DamageReportStatus::Open, DamageReportStatus::PendingVerification], true)
            && ! $this->repair()->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
