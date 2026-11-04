<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EquipmentStatus: string implements HasColor, HasLabel
{
    case FitForUse = 'fit_for_use';
    case CalibrationDue = 'calibration_due';
    case Overdue = 'overdue';
    case UnderTesting = 'under_testing';
    case UnderRepair = 'under_repair';
    case Unfit = 'unfit';

    /** Statuses in which a calibration result can be recorded (PRD §F2 & §F4). */
    public const CALIBRATABLE = [self::FitForUse, self::CalibrationDue, self::Overdue, self::UnderTesting];

    public function getLabel(): string
    {
        return match ($this) {
            self::FitForUse => 'Laik pakai',
            self::CalibrationDue => 'Perlu kalibrasi',
            self::Overdue => 'Terlambat',
            self::UnderTesting => 'Dalam uji & kalibrasi',
            self::UnderRepair => 'Dalam perbaikan',
            self::Unfit => 'Tidak laik pakai',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FitForUse => 'success',
            self::CalibrationDue => 'warning',
            self::Overdue => 'danger',
            self::UnderTesting => 'info',
            self::UnderRepair => 'gray',
            self::Unfit => 'danger',
        };
    }

    public function isDangerousToUse(): bool
    {
        return in_array($this, [self::Overdue, self::Unfit], true);
    }
}
