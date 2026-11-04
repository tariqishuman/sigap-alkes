<?php

namespace App\Filament\Resources\TestingInstitutions\Pages;

use App\Filament\Resources\TestingInstitutions\TestingInstitutionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTestingInstitutions extends ListRecords
{
    protected static string $resource = TestingInstitutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
