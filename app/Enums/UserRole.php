<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case SuperAdmin = 'super_admin';
    case AdminIpsrs = 'admin_ipsrs';
    case Teknisi = 'teknisi';
    case KepalaRuangan = 'kepala_ruangan';
    case Manajemen = 'manajemen';
    case BadanKalibrasi = 'badan_kalibrasi';
    case Magang = 'magang';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::AdminIpsrs => 'Admin IPSRS',
            self::Teknisi => 'Teknisi Elektromedis',
            self::KepalaRuangan => 'Kepala Ruangan',
            self::Manajemen => 'Manajemen RS',
            self::BadanKalibrasi => 'Badan Kalibrasi',
            self::Magang => 'Magang',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::AdminIpsrs => 'primary',
            self::Teknisi => 'info',
            self::KepalaRuangan => 'warning',
            self::Manajemen => 'success',
            self::BadanKalibrasi => 'info',
            self::Magang => 'gray',
        };
    }
}
