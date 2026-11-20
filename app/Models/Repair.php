<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'damage_report_id',
    'technician_id',
    'action',
    'started_at',
    'finished_at',
    'labor_cost',
])]
class Repair extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'labor_cost' => 'decimal:2',
        ];
    }

    public function damageReport(): BelongsTo
    {
        return $this->belongsTo(DamageReport::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function repairSpareParts(): HasMany
    {
        return $this->hasMany(RepairSparePart::class);
    }

    public function spareParts(): BelongsToMany
    {
        return $this->belongsToMany(SparePart::class, 'repair_spare_part')
            ->withPivot(['id', 'quantity', 'unit_price'])
            ->withTimestamps();
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }

    /**
     * Sum of (quantity * unit_price) across all spare parts in this repair, using bcmath.
     */
    public function sparePartsCost(): string
    {
        $this->loadMissing('repairSpareParts');

        $total = '0.00';

        foreach ($this->repairSpareParts as $item) {
            $line = bcmul((string) $item->quantity, bcadd((string) $item->unit_price, '0', 2), 2);
            $total = bcadd($total, $line, 2);
        }

        return $total;
    }

    /**
     * Total repair cost = labor_cost + sum(quantity * unit_price), using bcmath (PRD §F3).
     */
    public function totalCost(): string
    {
        $labor = bcadd((string) ($this->labor_cost ?? '0.00'), '0', 2);

        return bcadd($labor, $this->sparePartsCost(), 2);
    }

    public function getTotalCostAttribute(): string
    {
        return $this->totalCost();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
