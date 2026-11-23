<?php

namespace App\Providers;

use App\Models\Startup;
use App\Models\User;
use App\Policies\EquipePolicy;
use App\Policies\StartupPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Startup::class, StartupPolicy::class);
        Gate::policy(User::class, EquipePolicy::class);
    }
}
