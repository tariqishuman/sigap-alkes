<?php

namespace App\Filament\Resources\EquipmentTypes;

use App\Filament\Resources\EquipmentTypes\Pages\CreateEquipmentType;
use App\Filament\Resources\EquipmentTypes\Pages\EditEquipmentType;
use App\Filament\Resources\EquipmentTypes\Pages\ListEquipmentTypes;
use App\Filament\Resources\EquipmentTypes\Schemas\EquipmentTypeForm;
use App\Filament\Resources\EquipmentTypes\Tables\EquipmentTypesTable;
use App\Models\EquipmentType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EquipmentTypeResource extends Resource
{
    protected static ?string $model = EquipmentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'Jenis Alat';

    protected static ?string $modelLabel = 'Jenis Alat';

    protected static ?string $pluralModelLabel = 'Jenis Alat';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EquipmentTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipmentTypes::route('/'),
            'create' => CreateEquipmentType::route('/create'),
            'edit' => EditEquipmentType::route('/{record}/edit'),
        ];
    }
}
