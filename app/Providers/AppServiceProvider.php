<?php

namespace App\Providers;

use App\Access\CurrentCredentialUserProvider;
use App\Access\Models\Customer;
use App\Access\Policies\CustomerPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // No speculative module services.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('access-eloquent', fn ($app, array $config) => new CurrentCredentialUserProvider($app['hash'], $config['model']));
        Gate::policy(Customer::class, CustomerPolicy::class);
    }
}
