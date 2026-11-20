<?php

namespace App\Models;

use App\Enums\PreventiveMaintenanceResult;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'equipment_id',
    'performed_at',
    'performed_by',
    'checklist_results',
    'result',
    'notes',
    'next_due_at',
])]
class PreventiveMaintenance extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'performed_at' => 'date',
            'next_due_at' => 'date',
            'checklist_results' => 'array',
            'result' => PreventiveMaintenanceResult::class,
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function hasUnhealthyItems(): bool
    {
        foreach ($this->checklist_results ?? [] as $row) {
            if (($row['status'] ?? 'good') === 'needs_attention') {
                return true;
            }
        }

        return false;
    }

    public function unhealthyItemsSummary(): string
    {
        $items = [];

        foreach ($this->checklist_results ?? [] as $row) {
            if (($row['status'] ?? 'good') === 'needs_attention') {
                $label = trim((string) ($row['item'] ?? 'Pemeriksaan'));
                $note = trim((string) ($row['note'] ?? ''));
                $items[] = $note !== '' ? "{$label} ({$note})" : $label;
            }
        }

        if ($items === []) {
            return '';
        }

        return 'Temuan pemeliharaan preventif tidak baik: '.implode('; ', $items).'.';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
