<?php

namespace App\Filament\Resources\PreventiveMaintenances\Schemas;

use App\Models\Equipment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class PreventiveMaintenanceForm
{
    /**
     * Build mandatory checklist rows for a given equipment ID.
     * Condition has NO default value so the technician must explicitly select Baik / Tidak baik.
     *
     * @return array<int, array{item: string, status: null, note: null}>
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
            'status' => null,
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
                                    $set('mandatory_checklist', self::defaultChecklistForEquipment((int) $state));
                                }
                            })
                            ->required(),
                        DatePicker::make('performed_at')
                            ->label('Tanggal Pelaksanaan')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                    ]),
                Section::make('Daftar Periksa Wajib (Standar Jenis Alat)')
                    ->description('Setiap butir wajib dipilih kondisinya (Baik atau Tidak baik). Catatan wajib diisi bila memilih Tidak baik.')
                    ->schema([
                        Repeater::make('mandatory_checklist')
                            ->label('Daftar pemeriksaan wajib')
                            ->hiddenLabel()
                            ->default(fn (): array => self::defaultChecklistForEquipment($preselectedEquipmentId))
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                Hidden::make('item')->required(),
                                Placeholder::make('item_label')
                                    ->label('Butir Pemeriksaan')
                                    ->content(fn (Get $get): HtmlString => new HtmlString(
                                        '<span class="font-medium text-[#13262B]">'.e((string) ($get('item') ?? '-')).'</span>'
                                    ))
                                    ->columnSpan(5),
                                Radio::make('status')
                                    ->label('Kondisi')
                                    ->options([
                                        'good' => 'Baik',
                                        'needs_attention' => 'Tidak baik',
                                    ])
                                    ->inline()
                                    ->live()
                                    ->required()
                                    ->columnSpan(3),
                                TextInput::make('note')
                                    ->label('Catatan')
                                    ->placeholder(fn (Get $get): string => $get('status') === 'needs_attention'
                                        ? 'Wajib isi catatan temuan...'
                                        : 'Opsional')
                                    ->required(fn (Get $get): bool => $get('status') === 'needs_attention')
                                    ->maxLength(500)
                                    ->columnSpan(4),
                            ])
                            ->columns(12)
                            ->required(),
                    ]),
                Section::make('Butir Pemeriksaan Tambahan & Catatan Umum')
                    ->description('Opsional — tambahkan butir pemeriksaan ekstra di luar daftar standar jenis alat jika diperlukan.')
                    ->schema([
                        Repeater::make('additional_checklist')
                            ->label('Butir tambahan')
                            ->hiddenLabel()
                            ->defaultItems(0)
                            ->addActionLabel('Tambah butir pemeriksaan')
                            ->schema([
                                TextInput::make('item')
                                    ->label('Butir Pemeriksaan Tambahan')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(5),
                                Radio::make('status')
                                    ->label('Kondisi')
                                    ->options([
                                        'good' => 'Baik',
                                        'needs_attention' => 'Tidak baik',
                                    ])
                                    ->inline()
                                    ->live()
                                    ->required()
                                    ->columnSpan(3),
                                TextInput::make('note')
                                    ->label('Catatan')
                                    ->placeholder(fn (Get $get): string => $get('status') === 'needs_attention'
                                        ? 'Wajib isi catatan temuan...'
                                        : 'Opsional')
                                    ->required(fn (Get $get): bool => $get('status') === 'needs_attention')
                                    ->maxLength(500)
                                    ->columnSpan(4),
                            ])
                            ->columns(12),
                        Textarea::make('notes')
                            ->label('Catatan Umum Pemeliharaan')
                            ->placeholder('Ringkasan kondisi alat atau tindakan ringan yang dilakukan...')
                            ->rows(3)
                            ->maxLength(2000),
                    ]),
            ]);
    }
}
