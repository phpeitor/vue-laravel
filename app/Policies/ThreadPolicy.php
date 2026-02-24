<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Thread;

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

    public function close(User $user, Thread $thread): bool
    {
        return $user->can('chat');
        // Si luego quieres permiso específico:
        // return $user->can('chat.close');
    }
}
