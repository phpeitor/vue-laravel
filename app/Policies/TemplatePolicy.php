<?php

namespace App\Policies;

use App\Models\Template;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TemplatePolicy
{
    public function viewAny(User $user)
    {
        return $user->can('templates');
    }

    public function create(User $user)
    {
        return $user->can('add template');
    }

    public function update(User $user, Template $template)
    {
        return $user->can('edit template');
    }

    public function delete(User $user, Template $template)
    {
        return $user->can('delete template');
    }
}
