<?php

namespace App\Filament\Resources\Calibrations\Pages;

use App\Filament\Resources\Calibrations\CalibrationResource;
use App\Services\CalibrationService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCalibration extends EditRecord
{
    protected static string $resource = CalibrationResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return CalibrationResource::withFormErrors(
            fn () => app(CalibrationService::class)->update($record, $data),
        );
    }
}
