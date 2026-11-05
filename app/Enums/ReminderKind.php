<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReminderKind: string implements HasColor, HasLabel
{
    case H30 = 'h30';
    case H7 = 'h7';
    case H0 = 'h0';

    /**
     * The most urgent reminder that applies. Using thresholds instead of exact days
     * means a day missed by the scheduler is caught up on the next run.
     */
    public static function forDaysLeft(int $daysLeft): ?self
    {
        return match (true) {
            $daysLeft <= 0 => self::H0,
            $daysLeft <= 7 => self::H7,
            $daysLeft <= 30 => self::H30,
            default => null,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::H30 => 'H-30',
            self::H7 => 'H-7',
            self::H0 => 'H-0',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::H30 => 'info',
            self::H7 => 'warning',
            self::H0 => 'danger',
        };
    }
}
