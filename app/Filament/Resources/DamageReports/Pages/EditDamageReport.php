<?php

namespace App\Filament\Resources\DamageReports\Pages;

use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Services\DamageReportService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDamageReport extends EditRecord
{
    protected static string $resource = DamageReportResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DamageReportResource::withFormErrors(
            fn () => app(DamageReportService::class)->update($record, $data),
        );
    }
}
