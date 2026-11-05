<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(AuthUser $authUser, ?User $record = null): bool
    {
        return $authUser->can('View:User');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:User');
    }

    public function update(AuthUser $authUser, ?User $record = null): bool
    {
        if (! $authUser->can('Update:User')) {
            return false;
        }

        // Hanya super_admin yang boleh mengubah akun milik super_admin
        if ($record?->hasRole(UserRole::SuperAdmin->value) && ! $authUser->hasRole(UserRole::SuperAdmin->value)) {
            return false;
        }

        return true;
    }

    public function delete(AuthUser $authUser, ?User $record = null): bool
    {
        if (! $authUser->can('Delete:User')) {
            return false;
        }

        // Akun super_admin dan akun diri sendiri tidak boleh dihapus
        if ($record !== null && ($record->hasRole(UserRole::SuperAdmin->value) || $authUser->getKey() === $record->getKey())) {
            return false;
        }

        return true;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:User');
    }

    public function restore(AuthUser $authUser, ?User $record = null): bool
    {
        return $authUser->can('Restore:User');
    }

    public function forceDelete(AuthUser $authUser, ?User $record = null): bool
    {
        if (! $authUser->can('ForceDelete:User')) {
            return false;
        }

        if ($record !== null && ($record->hasRole(UserRole::SuperAdmin->value) || $authUser->getKey() === $record->getKey())) {
            return false;
        }

        return true;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:User');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:User');
    }

    public function replicate(AuthUser $authUser, ?User $record = null): bool
    {
        if (! $authUser->can('Replicate:User')) {
            return false;
        }

        if ($record?->hasRole(UserRole::SuperAdmin->value) && ! $authUser->hasRole(UserRole::SuperAdmin->value)) {
            return false;
        }

        return true;
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:User');
    }
}
