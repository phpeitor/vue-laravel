<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Models\Thread;
use App\Policies\ThreadPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Thread::class => ThreadPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
