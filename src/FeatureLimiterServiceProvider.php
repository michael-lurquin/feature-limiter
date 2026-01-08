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
        $this->publishes([
            __DIR__ . '/../config/feature-limiter.php' => $this->app->configPath('feature-limiter.php'),
        ], 'feature-limiter-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
