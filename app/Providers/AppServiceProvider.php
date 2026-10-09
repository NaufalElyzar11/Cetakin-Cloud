<?php

namespace App\Providers;

use App\Access\Models\Customer;
use App\Access\Policies\CustomerPolicy;
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
        Gate::policy(Customer::class, CustomerPolicy::class);
    }
}
