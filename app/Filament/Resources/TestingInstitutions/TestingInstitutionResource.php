<?php

namespace App\Filament\Resources\TestingInstitutions;

use App\Filament\Resources\TestingInstitutions\Pages\CreateTestingInstitution;
use App\Filament\Resources\TestingInstitutions\Pages\EditTestingInstitution;
use App\Filament\Resources\TestingInstitutions\Pages\ListTestingInstitutions;
use App\Filament\Resources\TestingInstitutions\Schemas\TestingInstitutionForm;
use App\Filament\Resources\TestingInstitutions\Tables\TestingInstitutionsTable;
use App\Models\TestingInstitution;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TestingInstitutionResource extends Resource
{
    protected static ?string $model = TestingInstitution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'Lembaga Penguji';

    protected static ?string $modelLabel = 'Lembaga Penguji';

    protected static ?string $pluralModelLabel = 'Lembaga Penguji';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TestingInstitutionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestingInstitutionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTestingInstitutions::route('/'),
            'create' => CreateTestingInstitution::route('/create'),
            'edit' => EditTestingInstitution::route('/{record}/edit'),
        ];
    }
}
