<?php

namespace App\Policies;

use App\Models\User;

class ThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('chat');
    }

    public function view(User $user): bool
    {
        return $user->can('chat');
    }
}
