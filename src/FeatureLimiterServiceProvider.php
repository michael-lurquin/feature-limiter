<?php

namespace MichaelLurquin\FeatureLimiter;

use Illuminate\Support\ServiceProvider;

class FeatureLimiterServiceProvider extends ServiceProvider
{
    /**
     * Setup the configuration for FeatureLimiter.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/feature-limiter.php', 'feature-limiter'
        );
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ( $this->app->runningInConsole() )
        {
            $this->publishes([
                __DIR__ . '/../config/feature-limiter.php' => $this->app->configPath('feature-limiter.php'),
            ], 'feature-limiter-config');

            $publishesMigrationsMethod = method_exists($this, 'publishesMigrations')
                ? 'publishesMigrations'
                : 'publishes';

            $this->{$publishesMigrationsMethod}([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'feature-limiter-migrations');
        }
    }
}
