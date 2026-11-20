<?php

namespace App\Filament\Resources\DamageReports;

use App\Enums\DamageReportStatus;
use App\Enums\UserRole;
use App\Filament\Resources\DamageReports\Pages\CreateDamageReport;
use App\Filament\Resources\DamageReports\Pages\EditDamageReport;
use App\Filament\Resources\DamageReports\Pages\ListDamageReports;
use App\Filament\Resources\DamageReports\Schemas\DamageReportForm;
use App\Filament\Resources\DamageReports\Tables\DamageReportsTable;
use App\Models\DamageReport;
use BackedEnum;
use Closure;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class DamageReportResource extends Resource
{
    protected static ?string $model = DamageReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::ExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Alat';

    protected static ?string $navigationLabel = 'Laporan Kerusakan';

    protected static ?string $modelLabel = 'Laporan Kerusakan';

    protected static ?string $pluralModelLabel = 'Laporan Kerusakan';

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $open = static::getEloquentQuery()
            ->where('status', DamageReportStatus::Open)
            ->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'equipment.room',
            'reportedByUser',
            'repair.technician',
            'repair.repairSpareParts',
        ]);

        $user = Auth::user();

        if (
            $user
            && $user->hasRole(UserRole::KepalaRuangan->value)
            && ! $user->hasAnyRole([
                UserRole::SuperAdmin->value,
                UserRole::AdminIpsrs->value,
                UserRole::Teknisi->value,
                UserRole::Manajemen->value,
            ])
        ) {
            $query->whereHas('equipment', fn (Builder $q) => $q->where('room_id', $user->room_id ?? 0));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return DamageReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DamageReportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDamageReports::route('/'),
            'create' => CreateDamageReport::route('/create'),
            'edit' => EditDamageReport::route('/{record}/edit'),
        ];
    }

    /**
     * Run a DamageReportService call, showing its validation errors under the form fields.
     */
    public static function withFormErrors(Closure $callback): DamageReport
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
