<?php

namespace App\Filament\Resources\DamageReports\Tables;

use App\Enums\DamageReportStatus;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Filament\Resources\Repairs\RepairResource;
use App\Models\DamageReport;
use App\Models\Room;
use App\Services\DamageReportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DamageReportsTable
{
    public static function configure(Table $table, bool $forEquipmentRelation = false): Table
    {
        $columns = [
            TextColumn::make('reported_at')
                ->label('Waktu Lapor')
                ->dateTime('d M Y H:i')
                ->sortable(),
        ];

        if (! $forEquipmentRelation) {
            $columns[] = TextColumn::make('equipment.name')
                ->label('Alat')
                ->weight('medium')
                ->limit(28)
                ->searchable(['name', 'inventory_code'])
                ->description(fn (DamageReport $record): string => sprintf(
                    '%s • %s',
                    $record->equipment?->inventory_code ?? '-',
                    $record->equipment?->room?->name ?? '-',
                ));
        }

        $columns = array_merge($columns, [
            TextColumn::make('complaint')
                ->label('Keluhan')
                ->limit(38)
                ->wrap()
                ->searchable()
                ->description(function (DamageReport $record): ?string {
                    $parts = [];
                    if (! $record->is_safe_to_use) {
                        $parts[] = 'Tidak aman dipakai';
                    }
                    $notesCount = $record->notes->count();
                    if ($notesCount > 0) {
                        $parts[] = "+{$notesCount} keterangan tambahan";
                    }

                    return $parts !== [] ? implode(' • ', $parts) : null;
                }),
            TextColumn::make('reportedByUser.name')
                ->label('Pelapor')
                ->limit(18)
                ->placeholder('-'),
            TextColumn::make('is_safe_to_use')
                ->label('Keamanan')
                ->badge()
                ->formatStateUsing(fn (bool $state): string => $state ? 'Masih aman' : 'Tidak aman')
                ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->sortable(),
            TextColumn::make('repair.technician.name')
                ->label('Teknisi')
                ->placeholder('-')
                ->toggleable(isToggledHiddenByDefault: ! $forEquipmentRelation),
            TextColumn::make('repair_total_cost')
                ->label('Biaya Perbaikan')
                ->visible(fn (): bool => Auth::user()?->canViewCosts() ?? false)
                ->state(fn (DamageReport $record): string => $record->repair
                    ? 'Rp'.number_format((float) $record->repair->totalCost(), 0, ',', '.')
                    : '-')
                ->toggleable(isToggledHiddenByDefault: ! $forEquipmentRelation),
        ]);

        $filters = [
            SelectFilter::make('status')
                ->label('Status')
                ->options(DamageReportStatus::class),
        ];

        if (! $forEquipmentRelation) {
            $filters[] = SelectFilter::make('room_id')
                ->label('Ruangan')
                ->options(fn (): array => Room::query()->orderBy('name')->pluck('name', 'id')->all())
                ->query(function (Builder $query, array $data): Builder {
                    if (blank($data['value'] ?? null)) {
                        return $query;
                    }

                    return $query->whereHas('equipment', fn (Builder $q) => $q->where('room_id', $data['value']));
                });
        }

        return $table
            ->defaultSort('reported_at', 'desc')
            ->columns($columns)
            ->filters($filters)
            ->recordActions([
                Action::make('verify_unsafe')
                    ->label('Verifikasi')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('warning')
                    ->visible(function (DamageReport $record): bool {
                        $user = Auth::user();

                        return $user !== null
                            && $record->isPendingVerification()
                            && app(DamageReportService::class)->canManageReportVerification($user);
                    })
                    ->modalHeading('Verifikasi Laporan Tidak Aman Dipakai')
                    ->modalSubmitActionLabel('Simpan verifikasi')
                    ->form([
                        Radio::make('decision')
                            ->label('Hasil verifikasi')
                            ->options([
                                'confirm' => 'Benar tidak aman — ubah status alat menjadi Tidak laik pakai',
                                'reject' => 'Tolak laporan',
                            ])
                            ->default('confirm')
                            ->live()
                            ->required(),
                        Textarea::make('rejected_reason')
                            ->label('Alasan penolakan')
                            ->rows(3)
                            ->required(fn (Get $get): bool => $get('decision') === 'reject')
                            ->visible(fn (Get $get): bool => $get('decision') === 'reject'),
                    ])
                    ->action(function (DamageReport $record, array $data): void {
                        try {
                            app(DamageReportService::class)->verifyUnsafeReport(
                                $record,
                                $data['decision'] === 'confirm',
                                Auth::user(),
                                $data['rejected_reason'] ?? null,
                            );

                            Notification::make()
                                ->success()
                                ->title($data['decision'] === 'confirm' ? 'Laporan diverifikasi tidak aman' : 'Laporan ditolak')
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Gagal memverifikasi laporan')
                                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                ->send();
                        }
                    }),
                Action::make('start_repair')
                    ->label('Perbaiki')
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->color('primary')
                    ->visible(fn (DamageReport $record): bool => $record->isOpen()
                        && ($record->equipment?->is_active ?? false)
                        && RepairResource::canCreate())
                    ->url(fn (DamageReport $record): string => RepairResource::getUrl('create', ['damage_report_id' => $record->id])),
                ActionGroup::make([
                    Action::make('view_detail')
                        ->label('Lihat detail')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->modalHeading('Detail Laporan Kerusakan')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Tutup')
                        ->modalContent(fn (DamageReport $record) => view('filament.damage-reports.detail-modal', [
                            'record' => $record->loadMissing(['equipment.room', 'reportedByUser', 'verifiedByUser', 'closedByUser', 'notes.reportedByUser']),
                        ])),
                    Action::make('close_without_repair')
                        ->label('Tutup tanpa perbaikan')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->visible(function (DamageReport $record): bool {
                            $user = Auth::user();

                            return $user !== null
                                && $record->canBeClosedWithoutRepair()
                                && app(DamageReportService::class)->canManageReportVerification($user);
                        })
                        ->modalHeading('Tutup Laporan Tanpa Perbaikan')
                        ->modalDescription('Gunakan bila tidak ditemukan kerusakan (mis. salah pakai atau alarm palsu). Bila alat menjadi Tidak laik karena laporan ini, status alat akan dievaluasi kembali.')
                        ->modalSubmitActionLabel('Tutup laporan')
                        ->form([
                            Textarea::make('reason')
                                ->label('Alasan penutupan')
                                ->placeholder('Contoh: Salah pemasangan sensor, setelah dicek alat normal.')
                                ->rows(3)
                                ->minLength(5)
                                ->maxLength(1000)
                                ->required(),
                        ])
                        ->action(function (DamageReport $record, array $data): void {
                            try {
                                app(DamageReportService::class)->closeWithoutRepair($record, $data['reason'], Auth::user());

                                Notification::make()
                                    ->success()
                                    ->title('Laporan ditutup tanpa perbaikan')
                                    ->send();
                            } catch (ValidationException $e) {
                                Notification::make()
                                    ->danger()
                                    ->title('Gagal menutup laporan')
                                    ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                    ->send();
                            }
                        }),
                    Action::make('view_repair')
                        ->label('Lihat perbaikan')
                        ->icon(Heroicon::OutlinedEye)
                        ->visible(fn (DamageReport $record): bool => $record->repair !== null && RepairResource::canView($record->repair))
                        ->url(fn (DamageReport $record): string => RepairResource::getUrl('view', ['record' => $record->repair])),
                    Action::make('edit')
                        ->label('Ubah keluhan')
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->visible(fn (DamageReport $record): bool => DamageReportResource::canEdit($record))
                        ->url(fn (DamageReport $record): string => DamageReportResource::getUrl('edit', ['record' => $record])),
                ])->tooltip('Aksi lainnya'),
            ])
            ->emptyState(view('filament.tables.empty-state', [
                'image' => 'empty-repair',
                'title' => 'Tidak ada kerusakan. Semua alat aman.',
                'description' => 'Belum ada laporan kerusakan yang tercatat untuk filter ini.',
            ]));
    }
}
