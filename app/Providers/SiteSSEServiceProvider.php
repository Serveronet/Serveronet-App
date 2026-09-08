<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class SiteSSEServiceProvider extends BaseServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot() 
    {
        
    }

    /**
     * Register package services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('SiteSSE', function () {
            return $this->app->make(\App\Support\SiteSSE::class);
        });
    }
}
