<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TestingInstitutionKind: string implements HasColor, HasLabel
{
    case Bpafk = 'bpafk';
    case Swasta = 'swasta';
    case InternalRs = 'internal_rs';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bpafk => 'BPAFK',
            self::Swasta => 'Lab Kalibrasi Swasta',
            self::InternalRs => 'Internal RS',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Bpafk => 'primary',
            self::Swasta => 'info',
            self::InternalRs => 'gray',
        };
    }
}
