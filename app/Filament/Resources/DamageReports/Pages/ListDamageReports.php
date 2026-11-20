<?php

namespace App\Filament\Resources\DamageReports\Pages;

use App\Enums\DamageReportStatus;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\DamageReport;
use App\Services\DamageReportService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListDamageReports extends ListRecords
{
    protected static string $resource = DamageReportResource::class;

    public function mount(): void
    {
        parent::mount();

        $ackId = request()->integer('ack');
        $user = Auth::user();

        if ($ackId > 0 && $user && app(DamageReportService::class)->canManageReportVerification($user)) {
            $report = DamageReport::find($ackId);

            if ($report) {
                $result = app(DamageReportService::class)->acknowledge($report, $user);

                if ($result['acknowledged']) {
                    Notification::make()
                        ->success()
                        ->title('Anda tercatat menangani laporan ini')
                        ->body("Laporan #{$report->id} ditandai sedang ditangani oleh {$user->name}.")
                        ->send();
                } else {
                    $owner = $result['report']->acknowledgedByUser?->name ?? 'teknisi lain';
                    Notification::make()
                        ->info()
                        ->title("Sudah ditangani oleh {$owner}")
                        ->body("Laporan #{$report->id} sudah lebih dulu diambil oleh {$owner}.")
                        ->send();
                }
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Laporkan kerusakan'),
        ];
    }

    /**
     * Status filter tabs with live counts (§4.5).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $baseQuery = fn (): Builder => DamageReportResource::getEloquentQuery();

        return [
            'all' => Tab::make('Semua')
                ->badge((clone $baseQuery())->count()),
            'pending_verification' => Tab::make('Menunggu verifikasi')
                ->badge((clone $baseQuery())->where('status', DamageReportStatus::PendingVerification)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', DamageReportStatus::PendingVerification)),
            'open' => Tab::make('Terbuka')
                ->badge((clone $baseQuery())->where('status', DamageReportStatus::Open)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', DamageReportStatus::Open)),
            'in_progress' => Tab::make('Diproses')
                ->badge((clone $baseQuery())->where('status', DamageReportStatus::InProgress)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', DamageReportStatus::InProgress)),
            'resolved' => Tab::make('Selesai')
                ->badge((clone $baseQuery())->where('status', DamageReportStatus::Resolved)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', DamageReportStatus::Resolved)),
        ];
    }
}
