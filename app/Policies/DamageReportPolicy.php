<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DamageReport;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

// Reports cannot be deleted (audit trail). Only the reporter may edit while status is still open.
class DamageReportPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DamageReport');
    }

    public function view(AuthUser $authUser, DamageReport $damageReport): bool
    {
        if (! $authUser->can('View:DamageReport')) {
            return false;
        }

        if ($authUser->hasRole(UserRole::KepalaRuangan->value) && ! $authUser->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Teknisi->value,
            UserRole::Manajemen->value,
        ])) {
            $damageReport->loadMissing('equipment');

            return $authUser->room_id !== null
                && (int) $authUser->room_id === (int) $damageReport->equipment?->room_id;
        }

        return true;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DamageReport');
    }

    public function update(AuthUser $authUser, DamageReport $damageReport): bool
    {
        if (! $authUser->can('Update:DamageReport')) {
            return false;
        }

        return $damageReport->isOpen()
            && (int) $damageReport->reported_by === (int) $authUser->getKey();
    }

    public function delete(AuthUser $authUser, DamageReport $damageReport): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, DamageReport $damageReport): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
