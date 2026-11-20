<?php

namespace App\Filament\Resources\EquipmentMovements\Pages;

use App\Filament\Resources\EquipmentMovements\EquipmentMovementResource;
use App\Services\EquipmentMovementService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateEquipmentMovement extends CreateRecord
{
    protected static string $resource = EquipmentMovementResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return EquipmentMovementResource::withFormErrors(
            fn () => app(EquipmentMovementService::class)->move($data, Auth::id()),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
