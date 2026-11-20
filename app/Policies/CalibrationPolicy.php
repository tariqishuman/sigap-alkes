<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Calibration;
use App\Models\User;
use Illuminate\Foundation\Auth\User as AuthUser;

// No delete methods: Gate denies them, because calibrations are the audit trail behind status changes.
class CalibrationPolicy
{
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Calibration');
    }

    public function view(AuthUser $authUser, Calibration $calibration): bool
    {
        if ($authUser->can('View:Calibration') || $authUser->can('ViewAny:Calibration')) {
            if ($authUser instanceof User && $authUser->isBadanKalibrasiOnly()) {
                return $authUser->testing_institution_id !== null
                    && (int) $authUser->testing_institution_id === (int) $calibration->testing_institution_id;
            }

            return true;
        }

        if ($authUser instanceof User && $authUser->hasRole(UserRole::KepalaRuangan->value)) {
            $calibration->loadMissing('equipment');

            return $authUser->room_id !== null
                && (int) $authUser->room_id === (int) $calibration->equipment?->room_id;
        }

        return false;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Calibration');
    }

    public function update(AuthUser $authUser, Calibration $calibration): bool
    {
        if (! $authUser->can('Update:Calibration')) {
            return false;
        }

        if ($authUser instanceof User && $authUser->isBadanKalibrasiOnly()) {
            return $authUser->testing_institution_id !== null
                && (int) $authUser->testing_institution_id === (int) $calibration->testing_institution_id;
        }

        return true;
    }
}
