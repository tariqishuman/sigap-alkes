<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Enums\EquipmentStatus;
use App\Filament\Resources\Calibrations\CalibrationResource;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Filament\Resources\Equipment\EquipmentResource;
use App\Filament\Resources\EquipmentMovements\EquipmentMovementResource;
use App\Filament\Resources\PreventiveMaintenances\PreventiveMaintenanceResource;
use App\Models\Equipment;
use App\Services\EquipmentStatusService;
use App\Services\QrLabelService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewEquipment extends ViewRecord
{
    protected static string $resource = EquipmentResource::class;

    public function getTitle(): string
    {
        return 'Detail alat';
    }

    public function getHeading(): string
    {
        return 'Detail alat';
    }

    /**
     * Header actions per design.md §2.3 & §3.14: max 3 controls
     * (1 primary pill, 1 secondary pill, overflow menu "⋯"). No status/warning colors on buttons.
     *
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        $canCalibrate = CalibrationResource::canCreate();
        $canReportDamage = DamageReportResource::canCreate();
        $canMove = EquipmentMovementResource::canCreate();

        $primaryIsCalibration = $canCalibrate;
        $secondaryIsDamage = $primaryIsCalibration && $canReportDamage;
        $secondaryIsMove = ! $secondaryIsDamage && $canMove;

        $actions = [];

        if ($primaryIsCalibration) {
            $actions[] = Action::make('record_calibration')
                ->label('Catat hasil kalibrasi')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('primary')
                ->visible(fn (Equipment $record): bool => $record->is_active
                    && in_array($record->status, EquipmentStatus::CALIBRATABLE, true))
                ->url(fn (Equipment $record): string => CalibrationResource::getUrl('create', ['equipment_id' => $record->id]));
        } elseif ($canReportDamage) {
            $actions[] = Action::make('report_damage')
                ->label('Lapor kerusakan')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('primary')
                ->visible(fn (Equipment $record): bool => $record->is_active)
                ->url(fn (Equipment $record): string => DamageReportResource::getUrl('create', ['equipment_id' => $record->id]));
        }

        if ($secondaryIsDamage) {
            $actions[] = Action::make('report_damage')
                ->label('Lapor kerusakan')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('gray')
                ->visible(fn (Equipment $record): bool => $record->is_active)
                ->url(fn (Equipment $record): string => DamageReportResource::getUrl('create', ['equipment_id' => $record->id]));
        } elseif ($secondaryIsMove) {
            $actions[] = Action::make('move_equipment')
                ->label('Mutasi')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('gray')
                ->visible(fn (Equipment $record): bool => $record->is_active)
                ->url(fn (Equipment $record): string => EquipmentMovementResource::getUrl('create', ['equipment_id' => $record->id]));
        }

        $overflowActions = [
            Action::make('start_testing')
                ->label('Mulai uji & kalibrasi')
                ->icon(Heroicon::OutlinedBeaker)
                ->requiresConfirmation()
                ->modalHeading('Mulai uji & kalibrasi')
                ->modalDescription('Status alat akan berubah menjadi "Dalam uji & kalibrasi".')
                ->visible(fn (Equipment $record): bool => $record->is_active
                    && $record->status !== EquipmentStatus::UnderTesting
                    && in_array($record->status, EquipmentStatus::CALIBRATABLE, true)
                    && $canCalibrate)
                ->action(function (Equipment $record) {
                    app(EquipmentStatusService::class)->startTesting($record, Auth::id());

                    return redirect(static::getResource()::getUrl('view', ['record' => $record]));
                }),
        ];

        if (! $secondaryIsMove) {
            $overflowActions[] = Action::make('move_equipment')
                ->label('Mutasi alat')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->visible(fn (Equipment $record): bool => $record->is_active && $canMove)
                ->url(fn (Equipment $record): string => EquipmentMovementResource::getUrl('create', ['equipment_id' => $record->id]));
        }

        $overflowActions[] = Action::make('record_pm')
            ->label('Catat pemeliharaan')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->visible(fn (Equipment $record): bool => $record->is_active
                && (bool) $record->equipmentType?->pm_interval_months
                && PreventiveMaintenanceResource::canCreate())
            ->url(fn (Equipment $record): string => PreventiveMaintenanceResource::getUrl('create', ['equipment_id' => $record->id]));

        $overflowActions[] = Action::make('print_qr')
            ->label('Cetak label QR')
            ->icon(Heroicon::OutlinedQrCode)
            ->visible(fn (): bool => Auth::user()?->can('Update:Equipment') ?? false)
            ->action(fn (Equipment $record) => app(QrLabelService::class)->downloadLabelsPdf(
                collect([$record]),
                "label-qr-{$record->inventory_code}.pdf",
            ));

        $overflowActions[] = EditAction::make()
            ->label('Ubah');

        $actions[] = ActionGroup::make($overflowActions)
            ->icon(Heroicon::OutlinedEllipsisHorizontal)
            ->color('gray')
            ->button()
            ->label('')
            ->tooltip('Aksi lainnya');

        return $actions;
    }
}
