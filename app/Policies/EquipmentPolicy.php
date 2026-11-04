<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Equipment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EquipmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Equipment');
    }

    public function view(AuthUser $authUser, Equipment $equipment): bool
    {
        if (! $authUser->can('View:Equipment')) {
            return false;
        }

        if ($authUser->hasRole(UserRole::KepalaRuangan->value) && ! $authUser->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Teknisi->value,
        ])) {
            return $authUser->room_id !== null && (int) $authUser->room_id === (int) $equipment->room_id;
        }

        return true;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Equipment');
    }

    public function update(AuthUser $authUser, Equipment $equipment): bool
    {
        return $authUser->can('Update:Equipment');
    }

    public function delete(AuthUser $authUser, Equipment $equipment): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restore(AuthUser $authUser, Equipment $equipment): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, Equipment $equipment): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function replicate(AuthUser $authUser, Equipment $equipment): bool
    {
        return false;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return false;
    }
}
