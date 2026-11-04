<?php

namespace App\Filament\Resources\Calibrations;

use App\Filament\Resources\Calibrations\Pages\CreateCalibration;
use App\Filament\Resources\Calibrations\Pages\EditCalibration;
use App\Filament\Resources\Calibrations\Pages\ListCalibrations;
use App\Filament\Resources\Calibrations\Schemas\CalibrationForm;
use App\Filament\Resources\Calibrations\Tables\CalibrationsTable;
use App\Models\Calibration;
use BackedEnum;
use Closure;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class CalibrationResource extends Resource
{
    protected static ?string $model = Calibration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Kegiatan';

    protected static ?string $navigationLabel = 'Kalibrasi';

    protected static ?string $modelLabel = 'Kalibrasi';

    protected static ?string $pluralModelLabel = 'Kalibrasi';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return CalibrationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CalibrationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalibrations::route('/'),
            'create' => CreateCalibration::route('/create'),
            'edit' => EditCalibration::route('/{record}/edit'),
        ];
    }

    /**
     * Run a CalibrationService call, showing its validation errors under the form fields.
     */
    public static function withFormErrors(Closure $callback): Calibration
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
