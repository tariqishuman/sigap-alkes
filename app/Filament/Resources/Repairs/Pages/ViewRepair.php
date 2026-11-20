<?php

namespace App\Filament\Resources\Repairs\Pages;

use App\Filament\Resources\Repairs\RepairResource;
use App\Models\Repair;
use App\Services\RepairService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ViewRepair extends ViewRecord
{
    protected static string $resource = RepairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('finish_repair')
                ->label('Tandai perbaikan selesai')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (Repair $record): bool => ! $record->isFinished() && RepairResource::canEdit($record))
                ->fillForm(fn (Repair $record): array => [
                    'finished_at' => now(),
                    'action' => $record->action,
                ])
                ->form([
                    DateTimePicker::make('finished_at')
                        ->label('Waktu Selesai')
                        ->seconds(false)
                        ->required(),
                    Textarea::make('action')
                        ->label('Tindakan Perbaikan')
                        ->rows(4)
                        ->required(),
                ])
                ->action(function (Repair $record, array $data) {
                    app(RepairService::class)->finish($record, $data, Auth::id());

                    return redirect(static::getResource()::getUrl('view', ['record' => $record]));
                }),
            EditAction::make()
                ->label('Ubah')
                ->visible(fn (Repair $record): bool => RepairResource::canEdit($record)),
        ];
    }
}
