<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Calibration;
use Illuminate\Foundation\Auth\User as AuthUser;

// No delete methods: Gate denies them, because calibrations are the audit trail behind status changes.
class CalibrationPolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Calibration');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Calibration');
    }

    public function update(AuthUser $authUser, Calibration $calibration): bool
    {
        return $authUser->can('Update:Calibration');
    }
}
