<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TestingInstitution;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TestingInstitutionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TestingInstitution');
    }

    public function view(AuthUser $authUser, TestingInstitution $testingInstitution): bool
    {
        return $authUser->can('View:TestingInstitution');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TestingInstitution');
    }

    public function update(AuthUser $authUser, TestingInstitution $testingInstitution): bool
    {
        return $authUser->can('Update:TestingInstitution');
    }

    public function delete(AuthUser $authUser, TestingInstitution $testingInstitution): bool
    {
        return $authUser->can('Delete:TestingInstitution');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TestingInstitution');
    }

    public function restore(AuthUser $authUser, TestingInstitution $testingInstitution): bool
    {
        return $authUser->can('Restore:TestingInstitution');
    }

    public function forceDelete(AuthUser $authUser, TestingInstitution $testingInstitution): bool
    {
        return $authUser->can('ForceDelete:TestingInstitution');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TestingInstitution');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TestingInstitution');
    }

    public function replicate(AuthUser $authUser, TestingInstitution $testingInstitution): bool
    {
        return $authUser->can('Replicate:TestingInstitution');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TestingInstitution');
    }
}
