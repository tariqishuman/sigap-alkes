<?php

namespace App\Filament\Resources\TestingInstitutions\Pages;

use App\Filament\Resources\TestingInstitutions\TestingInstitutionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTestingInstitution extends EditRecord
{
    protected static string $resource = TestingInstitutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
