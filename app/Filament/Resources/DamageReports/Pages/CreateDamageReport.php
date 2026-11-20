<?php

namespace App\Filament\Resources\DamageReports\Pages;

use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Services\DamageReportService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateDamageReport extends CreateRecord
{
    protected static string $resource = DamageReportResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DamageReportResource::withFormErrors(
            fn () => app(DamageReportService::class)->report($data, Auth::user()),
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
