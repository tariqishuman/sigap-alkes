<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DamageReportStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::InProgress => 'Sedang diperbaiki',
            self::Resolved => 'Selesai',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::Resolved => 'success',
        };
    }
}
