<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Repair;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

// Finished repairs are locked; repairs can never be deleted.
class RepairPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Repair');
    }

    public function view(AuthUser $authUser, Repair $repair): bool
    {
        return $authUser->can('View:Repair');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Repair');
    }

    public function update(AuthUser $authUser, Repair $repair): bool
    {
        return $authUser->can('Update:Repair') && ! $repair->isFinished();
    }

    public function delete(AuthUser $authUser, Repair $repair): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }

    public function forceDelete(AuthUser $authUser, Repair $repair): bool
    {
        return false;
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
