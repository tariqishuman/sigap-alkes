<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CalibrationResult: string implements HasColor, HasLabel
{
    case Pass = 'pass';
    case Fail = 'fail';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pass => 'Lulus',
            self::Fail => 'Tidak lulus',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pass => 'success',
            self::Fail => 'danger',
        };
    }
}
