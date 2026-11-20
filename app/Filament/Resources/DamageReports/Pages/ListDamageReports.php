<?php

namespace App\Filament\Resources\DamageReports\Pages;

use App\Enums\DamageReportStatus;
use App\Filament\Resources\DamageReports\DamageReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDamageReports extends ListRecords
{
    protected static string $resource = DamageReportResource::class;

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
