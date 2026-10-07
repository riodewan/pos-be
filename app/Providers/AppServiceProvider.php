<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Core\Context\TenantContext::class, function () {
            return new \App\Core\Context\TenantContext();
        });

        $this->app->singleton(\App\Core\Context\StoreContext::class, function () {
            return new \App\Core\Context\StoreContext();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
