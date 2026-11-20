<?php

namespace App\Filament\Resources\PreventiveMaintenances\Pages;

use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\PreventiveMaintenances\PreventiveMaintenanceResource;
use App\Models\PreventiveMaintenance;
use App\Services\PreventiveMaintenanceService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePreventiveMaintenance extends CreateRecord
{
    protected static string $resource = PreventiveMaintenanceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(PreventiveMaintenanceService::class)->record($data, Auth::user());
    }

    protected function getCreatedNotification(): ?Notification
    {
        if ($this->record instanceof PreventiveMaintenance && $this->record->hasUnhealthyItems()) {
            $reportUrl = DamageReportResource::getUrl('create', [
                'equipment_id' => $this->record->equipment_id,
                'complaint' => $this->record->unhealthyItemsSummary(),
            ]);

            return Notification::make()
                ->warning()
                ->persistent()
                ->title('Pemeliharaan tersimpan — ada butir tidak baik')
                ->body('Status alat tidak otomatis berubah. Buat laporan kerusakan bila alat perlu ditindaklanjuti atau dihentikan pemakaiannya.')
                ->actions([
                    Action::make('create_report')
                        ->label('Buat laporan kerusakan')
                        ->button()
                        ->url($reportUrl),
                ]);
        }

        return Notification::make()
            ->success()
            ->title('Pemeliharaan preventif tersimpan');
    }

    protected function getRedirectUrl(): string
    {
        if ($this->record instanceof PreventiveMaintenance && $this->record->equipment_id) {
            return EquipmentResource::getUrl('view', ['record' => $this->record->equipment_id]);
        }

        return static::getResource()::getUrl('index');
    }
}
