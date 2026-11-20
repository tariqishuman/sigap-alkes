<?php

namespace App\Filament\Resources\DamageReports\Pages;

use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\DamageReport;
use App\Services\DamageReportService;
use Filament\Notifications\Notification;
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

    protected function getCreatedNotification(): ?Notification
    {
        if ($this->record instanceof DamageReport && $this->record->wasMergedIntoActiveReport) {
            return Notification::make()
                ->info()
                ->title('Keterangan ditambahkan ke laporan aktif')
                ->body('Alat ini sudah memiliki laporan kerusakan aktif. Laporan Anda ditambahkan sebagai catatan tambahan.');
        }

        return Notification::make()
            ->success()
            ->title('Laporan kerusakan tersimpan');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
