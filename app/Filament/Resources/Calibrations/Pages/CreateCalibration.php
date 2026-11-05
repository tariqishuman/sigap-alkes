<?php

namespace App\Filament\Resources\Calibrations\Pages;

use App\Filament\Resources\Calibrations\CalibrationResource;
use App\Services\CalibrationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateCalibration extends CreateRecord
{
    protected static string $resource = CalibrationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return CalibrationResource::withFormErrors(
            fn () => app(CalibrationService::class)->record($data, Auth::id()),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
