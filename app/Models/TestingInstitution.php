<?php

namespace App\Models;

use App\Enums\TestingInstitutionKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'kind', 'accreditation_number'])]
class TestingInstitution extends Model
{
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'kind' => TestingInstitutionKind::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'kind', 'accreditation_number'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
