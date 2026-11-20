<?php

namespace App\Filament\Resources\Equipment\RelationManagers;

use App\Filament\Resources\DamageReports\Tables\DamageReportsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class DamageReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'damageReports';

    protected static ?string $title = 'Kerusakan & Perbaikan';

    public function table(Table $table): Table
    {
        return DamageReportsTable::configure($table, forEquipmentRelation: true);
    }
}
