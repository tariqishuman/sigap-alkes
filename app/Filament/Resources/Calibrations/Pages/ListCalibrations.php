<?php

namespace App\Filament\Resources\Calibrations\Pages;

use App\Filament\Resources\Calibrations\CalibrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCalibrations extends ListRecords
{
    protected static string $resource = CalibrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat kalibrasi'),
        ];
    }
}
