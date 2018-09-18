<?php

namespace ItsMill3rTime\LaraAudit;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;

class LaraAuditServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'lekker');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'lekker');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {

            // Publishing the configuration file.
            // $this->publishes([
            //    __DIR__ . '/../config/lara-audit.php' => config_path('lara-audit.php'),
            //], 'lara-audit.config');
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lara-audit.php', 'lara-audit');

        // Register the service the package provides.
        $this->app->singleton('lara', function ($app) {
            return new LaraAudit;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['lara-audit'];
    }
}