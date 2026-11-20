<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PreventiveMaintenanceResult: string implements HasColor, HasLabel
{
    case Good = 'good';
    case NeedsFollowup = 'needs_followup';

    public function getLabel(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::NeedsFollowup => 'Perlu tindak lanjut',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Good => 'success',
            self::NeedsFollowup => 'warning',
        };
    }
}
