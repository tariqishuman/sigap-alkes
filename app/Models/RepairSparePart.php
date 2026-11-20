<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'repair_id',
    'spare_part_id',
    'quantity',
    'unit_price',
])]
class RepairSparePart extends Model
{
    protected $table = 'repair_spare_part';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function repair(): BelongsTo
    {
        return $this->belongsTo(Repair::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function lineTotal(): string
    {
        return bcmul((string) $this->quantity, bcadd((string) $this->unit_price, '0', 2), 2);
    }
}
