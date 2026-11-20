<?php

namespace App\Filament\Resources\Repairs\Schemas;

use App\Enums\DamageReportStatus;
use App\Enums\EquipmentStatus;
use App\Models\DamageReport;
use App\Models\SparePart;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RepairForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Perbaikan')
                    ->columns(2)
                    ->schema([
                        Select::make('damage_report_id')
                            ->label('Laporan Kerusakan')
                            ->relationship('damageReport', 'complaint', function (Builder $query, string $operation) {
                                $query->with('equipment');

                                if ($operation === 'create') {
                                    $query
                                        ->where('status', DamageReportStatus::Open)
                                        ->whereHas('equipment', fn (Builder $q) => $q
                                            ->where('is_active', true)
                                            ->whereIn('status', [
                                                EquipmentStatus::FitForUse,
                                                EquipmentStatus::Unfit,
                                                EquipmentStatus::CalibrationDue,
                                                EquipmentStatus::Overdue,
                                            ])
                                            ->whereDoesntHave('repairs', fn (Builder $rq) => $rq->whereNull('repairs.finished_at')));
                                }
                            })
                            ->getOptionLabelFromRecordUsing(fn (DamageReport $record): string => sprintf(
                                '%s (%s) — %s',
                                $record->equipment?->name ?? '-',
                                $record->equipment?->inventory_code ?? '-',
                                Str::limit($record->complaint, 50),
                            ))
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => request()->integer('damage_report_id') ?: null)
                            ->disabledOn('edit')
                            ->required()
                            ->columnSpanFull(),
                        Select::make('technician_id')
                            ->label('Teknisi')
                            ->relationship('technician', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => Auth::id())
                            ->required(),
                        TextInput::make('labor_cost')
                            ->label('Biaya Jasa')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        DateTimePicker::make('started_at')
                            ->label('Waktu Mulai')
                            ->default(now())
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('finished_at')
                            ->label('Waktu Selesai (Opsional)')
                            ->seconds(false)
                            ->helperText('Isi jika perbaikan sudah selesai. Data akan dikunci setelah disimpan.'),
                        Textarea::make('action')
                            ->label('Tindakan Perbaikan')
                            ->placeholder('Catat tindakan perbaikan yang dilakukan...')
                            ->rows(4)
                            ->maxLength(4000)
                            ->columnSpanFull(),
                    ]),

                Section::make('Suku Cadang yang Digunakan')
                    ->schema([
                        Repeater::make('spare_parts')
                            ->label('Daftar Suku Cadang')
                            ->defaultItems(0)
                            ->addActionLabel('Tambah suku cadang')
                            ->columns(3)
                            ->schema([
                                Select::make('spare_part_id')
                                    ->label('Suku Cadang')
                                    ->options(fn (): array => SparePart::query()
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn (SparePart $sp): array => [$sp->id => "{$sp->name} ({$sp->unit}) — Stok: {$sp->stock}"])
                                        ->all())
                                    ->searchable()
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                                TextInput::make('unit_price')
                                    ->label('Harga Satuan')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->minValue(0)
                                    ->default(0)
                                    ->required(),
                            ]),
                    ]),
            ]);
    }
}
