<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Thread;
use App\Models\Message;
use App\Models\User;
use App\Observers\ThreadObserver;
use App\Observers\MessageObserver;
use App\Observers\UserObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        
    }
    
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Thread::observe(ThreadObserver::class);
        Message::observe(MessageObserver::class);


        User::observe(UserObserver::class);

        Inertia::share([
            'config' => [
                'hsmBaseUrl' => config('services.hsm.base_url'),
            ],
        ]);

    }
}