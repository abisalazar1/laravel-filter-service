<?php

namespace Devespresso\DataFiltering;

use Illuminate\Support\ServiceProvider;

class DataFilteringServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/devespressoApi.php' => config_path('devespressoApi.php'),
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/devespressoApi.php',
            'devespressoApi'
        );
    }
}
