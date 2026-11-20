<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

// Column `stock` is intentionally omitted from fillable: always modify via SparePartStockService.
#[Fillable(['name', 'unit', 'min_stock'])]
class SparePart extends Model
{
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'stock' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SparePartMovement::class)->orderByDesc('moved_at')->orderByDesc('id');
    }

    public function isLowStock(): bool
    {
        return (int) $this->stock <= (int) $this->min_stock;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'unit', 'stock', 'min_stock'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
