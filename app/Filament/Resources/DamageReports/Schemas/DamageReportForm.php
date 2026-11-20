<?php

namespace App\Filament\Resources\DamageReports\Schemas;

use App\Enums\UserRole;
use App\Models\Equipment;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class DamageReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Laporan Kerusakan')
                    ->columns(2)
                    ->schema([
                        Select::make('equipment_id')
                            ->label('Alat')
                            ->relationship('equipment', 'name', function (Builder $query, string $operation) {
                                if ($operation === 'create') {
                                    $query->where('is_active', true);

                                    $user = Auth::user();
                                    if (
                                        $user
                                        && $user->hasRole(UserRole::KepalaRuangan->value)
                                        && ! $user->hasAnyRole([
                                            UserRole::SuperAdmin->value,
                                            UserRole::AdminIpsrs->value,
                                            UserRole::Teknisi->value,
                                        ])
                                    ) {
                                        $query->where('room_id', $user->room_id ?? 0);
                                    }
                                }
                            })
                            ->getOptionLabelFromRecordUsing(fn (Equipment $record): string => "{$record->name} ({$record->inventory_code})")
                            ->searchable(['name', 'inventory_code'])
                            ->preload()
                            ->default(fn (): ?int => request()->integer('equipment_id') ?: null)
                            ->disabledOn('edit')
                            ->required(),
                        DateTimePicker::make('reported_at')
                            ->label('Waktu Lapor')
                            ->default(now())
                            ->maxDate(now())
                            ->seconds(false)
                            ->disabledOn('edit')
                            ->required(),
                        Radio::make('is_safe_to_use')
                            ->label('Apakah alat masih aman dipakai?')
                            ->options([
                                1 => 'Ya, masih aman dipakai',
                                0 => 'Tidak, tidak aman dipakai',
                            ])
                            ->descriptions([
                                1 => 'Status alat tetap seperti saat ini sampai teknisi memeriksa.',
                                0 => 'Status alat langsung berubah menjadi Tidak laik pakai (untuk magang: menunggu verifikasi teknisi/Admin IPSRS).',
                            ])
                            ->boolean()
                            ->inline(false)
                            ->required()
                            ->disabledOn('edit')
                            ->columnSpanFull(),
                        Textarea::make('complaint')
                            ->label('Keluhan / Deskripsi Kerusakan')
                            ->placeholder('Jelaskan gejala kerusakan yang ditemukan pada alat (minimal 10 karakter)...')
                            ->rows(4)
                            ->minLength(10)
                            ->maxLength(2000)
                            ->required()
                            ->columnSpanFull(),
                        FileUpload::make('photos')
                            ->label('Foto Kerusakan (Opsional)')
                            ->helperText('Unggah maksimal 3 foto (JPG/PNG, maks 3 MB per foto). Hanya tampil di panel internal.')
                            ->disk('local')
                            ->directory('damage-report-photos')
                            ->visibility('private')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->multiple()
                            ->maxFiles(3)
                            ->maxSize(3072)
                            ->hiddenOn('edit')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
