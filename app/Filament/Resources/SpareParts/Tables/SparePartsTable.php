<?php

namespace App\Filament\Resources\SpareParts\Tables;

use App\Models\SparePart;
use App\Services\SparePartStockService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class SparePartsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Suku Cadang')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('Stok')
                    ->alignEnd()
                    ->formatStateUsing(fn (SparePart $record): string => number_format($record->stock, 0, ',', '.')." {$record->unit}")
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('min_stock')
                    ->label('Minimal')
                    ->alignEnd()
                    ->formatStateUsing(fn (SparePart $record): string => number_format($record->min_stock, 0, ',', '.')." {$record->unit}")
                    ->sortable(),
                TextColumn::make('stock_status')
                    ->label('Status Stok')
                    ->badge()
                    ->state(fn (SparePart $record): string => match (true) {
                        $record->stock <= 0 => 'Habis',
                        $record->isLowStock() => 'Menipis',
                        default => 'Aman',
                    })
                    ->color(fn (SparePart $record): string => match (true) {
                        $record->stock <= 0 => 'danger',
                        $record->isLowStock() => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('usage_30d')
                    ->label('Pemakaian 30 hari')
                    ->alignEnd()
                    ->state(function (SparePart $record): string {
                        $used = (int) abs((int) $record->movements()
                            ->where('type', 'out')
                            ->where('moved_at', '>=', now()->subDays(30))
                            ->sum('quantity'));

                        return $used > 0 ? number_format($used, 0, ',', '.')." {$record->unit}" : '–';
                    }),
            ])
            ->filters([])
            ->recordActions([
                Action::make('stock_in')
                    ->label('Tambah stok')
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('success')
                    ->visible(fn (): bool => Auth::user()?->can('Update:SparePart') ?? false)
                    ->form([
                        TextInput::make('quantity')
                            ->label('Jumlah Masuk')
                            ->integer()
                            ->minValue(1)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Keterangan (Pemasok / No. Dokumen)')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (SparePart $record, array $data): void {
                        app(SparePartStockService::class)->stockIn(
                            $record,
                            (int) $data['quantity'],
                            (string) $data['notes'],
                            Auth::id(),
                        );

                        Notification::make()
                            ->title('Stok berhasil ditambahkan')
                            ->success()
                            ->send();
                    }),
                ActionGroup::make([
                    Action::make('adjust_stock')
                        ->label('Sesuaikan stok')
                        ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                        ->color('gray')
                        ->visible(fn (): bool => Auth::user()?->can('Update:SparePart') ?? false)
                        ->fillForm(fn (SparePart $record): array => [
                            'new_stock' => $record->stock,
                        ])
                        ->form([
                            TextInput::make('new_stock')
                                ->label('Stok Aktual (Hasil Opname)')
                                ->integer()
                                ->minValue(0)
                                ->required(),
                            Textarea::make('notes')
                                ->label('Alasan Penyesuaian')
                                ->rows(3)
                                ->required(),
                        ])
                        ->action(function (SparePart $record, array $data): void {
                            app(SparePartStockService::class)->adjust(
                                $record,
                                (int) $data['new_stock'],
                                (string) $data['notes'],
                                Auth::id(),
                            );

                            Notification::make()
                                ->title('Stok berhasil disesuaikan')
                                ->success()
                                ->send();
                        }),
                    ViewAction::make()->label('Riwayat & detail'),
                    EditAction::make()->label('Ubah'),
                    DeleteAction::make()->label('Hapus'),
                ])->tooltip('Aksi lainnya'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Hapus terpilih'),
                ]),
            ])
            ->emptyState(view('filament.tables.empty-state', [
                'image' => 'empty-search',
                'title' => 'Belum ada data suku cadang',
                'description' => 'Tambahkan master suku cadang untuk mulai mencatat stok dan pemakaian perbaikan.',
            ]));
    }
}
