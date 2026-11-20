<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SparePartMovementType: string implements HasColor, HasLabel
{
    case In = 'in';
    case Out = 'out';
    case Return = 'return';
    case Adjustment = 'adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::In => 'Stok masuk',
            self::Out => 'Pemakaian',
            self::Return => 'Pengembalian',
            self::Adjustment => 'Penyesuaian',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'warning',
            self::Return => 'info',
            self::Adjustment => 'gray',
        };
    }
}
