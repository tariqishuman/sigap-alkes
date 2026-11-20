<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PreventiveMaintenance;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

// Preventive maintenance records cannot be hard-deleted (audit trail).
class PreventiveMaintenancePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PreventiveMaintenance');
    }

    public function view(AuthUser $authUser, PreventiveMaintenance $pm): bool
    {
        if (! $authUser->can('View:PreventiveMaintenance')) {
            return false;
        }

        if ($authUser->hasRole(UserRole::KepalaRuangan->value) && ! $authUser->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Teknisi->value,
            UserRole::Manajemen->value,
            UserRole::Magang->value,
        ])) {
            $pm->loadMissing('equipment');

            return $authUser->room_id !== null
                && (int) $authUser->room_id === (int) $pm->equipment?->room_id;
        }

        return true;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PreventiveMaintenance');
    }

    public function update(AuthUser $authUser, PreventiveMaintenance $pm): bool
    {
        return $authUser->can('Update:PreventiveMaintenance');
    }

    public function delete(AuthUser $authUser, PreventiveMaintenance $pm): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, PreventiveMaintenance $pm): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
