<?php

namespace App\Filament\Resources\Calibrations\Schemas;

use App\Enums\CalibrationResult;
use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CalibrationForm
{
    public static function configure(Schema $schema): Schema
    {
        // The form only guides input; CalibrationService enforces the rules.
        $isPass = fn (Get $get): bool => in_array($get('result'), [CalibrationResult::Pass, CalibrationResult::Pass->value], true);

        return $schema
            ->components([
                Section::make('Hasil Uji & Kalibrasi')
                    ->columns(2)
                    ->schema([
                        Select::make('equipment_id')
                            ->label('Alat')
                            ->relationship('equipment', 'name', function (Builder $query, string $operation) {
                                if ($operation === 'create') {
                                    $query->where('is_active', true)->whereIn('status', EquipmentStatus::CALIBRATABLE);
                                }
                            })
                            ->getOptionLabelFromRecordUsing(fn (Equipment $record): string => "{$record->name} ({$record->inventory_code})")
                            ->searchable(['name', 'inventory_code'])
                            ->preload()
                            ->default(fn (): ?int => request()->integer('equipment_id') ?: null)
                            ->disabledOn('edit')
                            ->required(),
                        Select::make('result')
                            ->label('Hasil')
                            ->options(CalibrationResult::class)
                            ->default(CalibrationResult::Pass->value)
                            ->live()
                            ->disabledOn('edit')
                            ->required(),
                        DatePicker::make('tested_at')
                            ->label('Tanggal Uji')
                            ->maxDate(today())
                            ->default(today())
                            ->required(),
                        Select::make('testing_institution_id')
                            ->label('Lembaga Penguji')
                            ->relationship('testingInstitution', 'name')
                            ->searchable()
                            ->preload()
                            ->default(fn (): ?int => Auth::user()?->isBadanKalibrasiOnly() ? Auth::user()->testing_institution_id : null)
                            ->disabled(fn (): bool => Auth::user()?->isBadanKalibrasiOnly() ?? false)
                            ->dehydrated(true)
                            ->required($isPass),
                        TextInput::make('certificate_number')
                            ->label('Nomor Sertifikat')
                            ->maxLength(255)
                            ->visible($isPass)
                            ->required($isPass),
                        DatePicker::make('valid_until')
                            ->label('Berlaku Sampai')
                            ->helperText('Paling lama tanggal uji ditambah interval kalibrasi jenis alat.')
                            ->visible($isPass)
                            ->required($isPass),
                        // Private disk: certificates are served through an authorized route.
                        FileUpload::make('certificate_path')
                            ->label('File Sertifikat (PDF)')
                            ->helperText('Unggah file PDF sertifikat kalibrasi (maks 5 MB). Wajib untuk hasil lulus.')
                            ->disk('local')
                            ->directory('calibration-certificates')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(5120)
                            ->visible($isPass)
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $isPass($get))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
