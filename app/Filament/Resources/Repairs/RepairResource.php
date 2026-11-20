<?php

namespace App\Filament\Resources\Repairs;

use App\Filament\Resources\Repairs\Pages\CreateRepair;
use App\Filament\Resources\Repairs\Pages\EditRepair;
use App\Filament\Resources\Repairs\Pages\ListRepairs;
use App\Filament\Resources\Repairs\Pages\ViewRepair;
use App\Filament\Resources\Repairs\Schemas\RepairForm;
use App\Filament\Resources\Repairs\Schemas\RepairInfolist;
use App\Filament\Resources\Repairs\Tables\RepairsTable;
use App\Models\Repair;
use BackedEnum;
use Closure;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class RepairResource extends Resource
{
    protected static ?string $model = Repair::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::WrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Kegiatan';

    protected static ?string $navigationLabel = 'Perbaikan';

    protected static ?string $modelLabel = 'Perbaikan';

    protected static ?string $pluralModelLabel = 'Perbaikan';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'damageReport.equipment.room',
            'technician',
            'repairSpareParts.sparePart',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return RepairForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RepairInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RepairsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRepairs::route('/'),
            'create' => CreateRepair::route('/create'),
            'view' => ViewRepair::route('/{record}'),
            'edit' => EditRepair::route('/{record}/edit'),
        ];
    }

    /**
     * Run a RepairService call, showing its validation errors under the form fields.
     */
    public static function withFormErrors(Closure $callback): Repair
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $field) => ["data.{$field}" => $messages])->all(),
            );
        }
    }
}
