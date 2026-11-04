<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EquipmentType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class EquipmentTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:EquipmentType');
    }

    public function view(AuthUser $authUser, EquipmentType $equipmentType): bool
    {
        return $authUser->can('View:EquipmentType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:EquipmentType');
    }

    public function update(AuthUser $authUser, EquipmentType $equipmentType): bool
    {
        return $authUser->can('Update:EquipmentType');
    }

    public function delete(AuthUser $authUser, EquipmentType $equipmentType): bool
    {
        return $authUser->can('Delete:EquipmentType');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:EquipmentType');
    }

    public function restore(AuthUser $authUser, EquipmentType $equipmentType): bool
    {
        return $authUser->can('Restore:EquipmentType');
    }

    public function forceDelete(AuthUser $authUser, EquipmentType $equipmentType): bool
    {
        return $authUser->can('ForceDelete:EquipmentType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:EquipmentType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:EquipmentType');
    }

    public function replicate(AuthUser $authUser, EquipmentType $equipmentType): bool
    {
        return $authUser->can('Replicate:EquipmentType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:EquipmentType');
    }
}
