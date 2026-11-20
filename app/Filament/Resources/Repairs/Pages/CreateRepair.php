<?php

namespace App\Filament\Resources\Repairs\Pages;

use App\Filament\Resources\Repairs\RepairResource;
use App\Services\RepairService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateRepair extends CreateRecord
{
    protected static string $resource = RepairResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return RepairResource::withFormErrors(
            fn () => app(RepairService::class)->start($data, Auth::id()),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
