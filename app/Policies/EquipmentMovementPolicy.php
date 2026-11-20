<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EquipmentMovement;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

// Equipment movements are immutable audit records and cannot be edited or deleted.
class EquipmentMovementPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EquipmentMovement');
    }

    public function view(AuthUser $authUser, EquipmentMovement $equipmentMovement): bool
    {
        return $authUser->can('View:EquipmentMovement');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EquipmentMovement');
    }

    public function update(AuthUser $authUser, EquipmentMovement $equipmentMovement): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, EquipmentMovement $equipmentMovement): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, EquipmentMovement $equipmentMovement): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
