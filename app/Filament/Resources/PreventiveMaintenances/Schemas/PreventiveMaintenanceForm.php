<?php

namespace App\Filament\Resources\PreventiveMaintenances\Schemas;

use App\Models\Equipment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PreventiveMaintenanceForm
{
    /**
     * Build default checklist rows for a given equipment ID.
     *
     * @return array<int, array{item: string, status: string, note: null}>
     */
    public static function defaultChecklistForEquipment(?int $equipmentId): array
    {
        $defaultItems = [
            'Kebersihan & kondisi fisik',
            'Kabel & konektor',
            'Baterai / catu daya',
            'Alarm berfungsi',
            'Uji fungsi dasar',
        ];

        if ($equipmentId) {
            $equipment = Equipment::with('equipmentType')->find($equipmentId);
            $typeChecklist = $equipment?->equipmentType?->pm_checklist;
            if (is_array($typeChecklist) && count($typeChecklist) > 0) {
                $defaultItems = array_values(array_filter($typeChecklist, fn ($v) => is_string($v) && trim($v) !== ''));
            }
        }

        return array_map(fn (string $item): array => [
            'item' => $item,
            'status' => 'good',
            'note' => null,
        ], $defaultItems);
    }

    public static function configure(Schema $schema): Schema
    {
        $preselectedEquipmentId = request()->integer('equipment_id') ?: null;

        return $schema
            ->components([
                Section::make('Informasi Pemeliharaan Preventif')
                    ->columns(2)
                    ->schema([
                        Select::make('equipment_id')
                            ->label('Alat')
                            ->relationship(
                                'equipment',
                                'name',
                                fn (Builder $query) => $query
                                    ->where('is_active', true)
                                    ->whereHas('equipmentType', fn (Builder $tq) => $tq->whereNotNull('pm_interval_months')->where('pm_interval_months', '>', 0)),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Equipment $record): string => "{$record->name} ({$record->inventory_code})")
                            ->searchable(['name', 'inventory_code'])
                            ->preload()
                            ->default($preselectedEquipmentId)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (filled($state)) {
                                    $set('checklist_results', self::defaultChecklistForEquipment((int) $state));
                                }
                            })
                            ->required(),
                        DatePicker::make('performed_at')
                            ->label('Tanggal Pelaksanaan')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                    ]),
                Section::make('Daftar Periksa (Checklist)')
                    ->description('Tandai setiap butir pemeriksaan. Jika ada butir Tidak baik, Anda akan ditawarkan langsung membuat laporan kerusakan setelah menyimpan.')
                    ->schema([
                        Repeater::make('checklist_results')
                            ->label('Daftar pemeriksaan')
                            ->hiddenLabel()
                            ->default(fn (): array => self::defaultChecklistForEquipment($preselectedEquipmentId))
                            ->schema([
                                TextInput::make('item')
                                    ->label('Butir Pemeriksaan')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(5),
                                Radio::make('status')
                                    ->label('Kondisi')
                                    ->options([
                                        'good' => 'Baik',
                                        'needs_attention' => 'Tidak baik',
                                    ])
                                    ->default('good')
                                    ->inline()
                                    ->required()
                                    ->columnSpan(3),
                                TextInput::make('note')
                                    ->label('Catatan')
                                    ->placeholder('Opsional')
                                    ->maxLength(500)
                                    ->columnSpan(4),
                            ])
                            ->columns(12)
                            ->addActionLabel('Tambah butir pemeriksaan')
                            ->minItems(1)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Catatan Umum Pemeliharaan')
                            ->placeholder('Ringkasan kondisi alat atau tindakan ringan yang dilakukan...')
                            ->rows(3)
                            ->maxLength(2000),
                    ]),
            ]);
    }
}
