<?php

namespace App\Filament\Resources\EquipmentMovements;

use App\Filament\Resources\EquipmentMovements\Pages\CreateEquipmentMovement;
use App\Filament\Resources\EquipmentMovements\Pages\ListEquipmentMovements;
use App\Filament\Resources\EquipmentMovements\Schemas\EquipmentMovementForm;
use App\Filament\Resources\EquipmentMovements\Tables\EquipmentMovementsTable;
use App\Models\EquipmentMovement;
use BackedEnum;
use Closure;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class EquipmentMovementResource extends Resource
{
    protected static ?string $model = EquipmentMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Kegiatan';

    protected static ?string $navigationLabel = 'Mutasi Alat';

    protected static ?string $modelLabel = 'Mutasi Alat';

    protected static ?string $pluralModelLabel = 'Mutasi Alat';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'equipment',
            'fromRoom',
            'toRoom',
            'movedByUser',
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return EquipmentMovementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipmentMovementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEquipmentMovements::route('/'),
            'create' => CreateEquipmentMovement::route('/create'),
        ];
    }

    /**
     * Run an EquipmentMovementService call, showing its validation errors under the form fields.
     */
    public static function withFormErrors(Closure $callback): EquipmentMovement
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
