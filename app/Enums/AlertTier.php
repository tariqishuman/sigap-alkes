<?php

namespace App\Enums;

enum AlertTier: string
{
    case Critical = 'critical';
    case Important = 'important';
    case Info = 'info';

    public function getLabel(): string
    {
        return match ($this) {
            self::Critical => 'Kritis',
            self::Important => 'Penting',
            self::Info => 'Info',
        };
    }
}
