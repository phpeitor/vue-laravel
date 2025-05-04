<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    
    public function viewAny(User $user)
    {
        return $user->can('users');
    }

    public function create(User $user)
    {
        return $user->can('add user');
    }

    public function update(User $user, User $model)
    {
        return $user->can('edit user');
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
