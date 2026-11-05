<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use App\Filament\Resources\Calibrations\Tables\CalibrationsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class CalibrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'calibrations';

    protected static ?string $title = 'Kalibrasi';

    public function table(Table $table): Table
    {
        return CalibrationsTable::configure($table);
    }
}
