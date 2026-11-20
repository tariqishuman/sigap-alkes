<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DamageReportStatus: string implements HasColor, HasLabel
{
    case PendingVerification = 'pending_verification';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::PendingVerification => 'Menunggu verifikasi',
            self::Open => 'Terbuka',
            self::InProgress => 'Sedang diperbaiki',
            self::Resolved => 'Selesai',
            self::Rejected => 'Ditolak',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PendingVerification => 'warning',
            self::Open => 'warning',
            self::InProgress => 'info',
            self::Resolved => 'success',
            self::Rejected => 'gray',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::PendingVerification,
            self::Open,
            self::InProgress,
        ], true);
    }
}
