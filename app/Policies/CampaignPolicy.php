<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CampaignPolicy
{
    public function viewAny(User $user)
    {
        return $user->can('campaigns');
    }

    public function create(User $user)
    {
        return $user->can('add campaign');
    }

    public function update(User $user, Campaign $campaign)
    {
        return $user->can('edit campaign');
    }

    public function delete(User $user, Campaign $campaign)
    {
        return $user->can('delete campaign');
    }
}