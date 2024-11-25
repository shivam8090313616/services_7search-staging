<?php

namespace App\Providers;

use App\Services\AccVerifiedService;
use Illuminate\Support\ServiceProvider;

class AccVerifiedServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Bind AccVerifiedService to the service container
        $this->app->singleton(AccVerifiedService::class, function ($app) {
            return new AccVerifiedService();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
