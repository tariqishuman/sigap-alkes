<?php

namespace App\Filament\Resources\SpareParts;

use App\Filament\Resources\SpareParts\Pages\CreateSparePart;
use App\Filament\Resources\SpareParts\Pages\EditSparePart;
use App\Filament\Resources\SpareParts\Pages\ListSpareParts;
use App\Filament\Resources\SpareParts\Pages\ViewSparePart;
use App\Filament\Resources\SpareParts\RelationManagers\StockMovementsRelationManager;
use App\Filament\Resources\SpareParts\Schemas\SparePartForm;
use App\Filament\Resources\SpareParts\Tables\SparePartsTable;
use App\Models\SparePart;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SparePartResource extends Resource
{
    protected static ?string $model = SparePart::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Kegiatan';

    protected static ?string $navigationLabel = 'Stok Suku Cadang';

    protected static ?string $modelLabel = 'Suku Cadang';

    protected static ?string $pluralModelLabel = 'Stok Suku Cadang';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $lowCount = SparePart::query()
            ->whereColumn('stock', '<=', 'min_stock')
            ->count();

        return $lowCount > 0 ? (string) $lowCount : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return SparePartForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SparePartsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StockMovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpareParts::route('/'),
            'create' => CreateSparePart::route('/create'),
            'view' => ViewSparePart::route('/{record}'),
            'edit' => EditSparePart::route('/{record}/edit'),
        ];
    }
}
