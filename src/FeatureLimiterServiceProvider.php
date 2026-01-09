<?php

namespace MichaelLurquin\FeatureLimiter;

use Illuminate\Support\ServiceProvider;
use MichaelLurquin\FeatureLimiter\FeatureLimiterManager;
use MichaelLurquin\FeatureLimiter\Billing\BillingManager;
use MichaelLurquin\FeatureLimiter\Support\PeriodResolver;
use MichaelLurquin\FeatureLimiter\Contracts\BillingProvider;
use MichaelLurquin\FeatureLimiter\Billing\CashierBillingProvider;
use MichaelLurquin\FeatureLimiter\Repositories\FeatureUsageRepository;

class FeatureLimiterServiceProvider extends ServiceProvider
{
    /**
     * Setup the configuration for FeatureLimiter.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/feature-limiter.php', 'feature-limiter');

        $this->app->singleton(BillingManager::class, fn ($app) => new BillingManager($app));

        $this->app->bind(BillingProvider::class, function ($app) {
            return $app->make(BillingManager::class)->default();
        });

        $this->app->bind(CashierBillingProvider::class, function ($app) {
            return new CashierBillingProvider(
                subscriptionName: $app['config']->get('feature-limiter.billing.cashier.subscription_name', 'default'),
                defaultPlanKey: $app['config']->get('feature-limiter.defaults.plan_key', 'free'),
            );
        });

        $this->app->singleton(PeriodResolver::class, fn () => new PeriodResolver());

        $this->app->singleton(FeatureUsageRepository::class, fn ($app) => new FeatureUsageRepository(
            $app->make(PeriodResolver::class),
        ));

        $this->app->singleton(FeatureLimiterManager::class, function ($app) {
            return new FeatureLimiterManager(
                $app->make(BillingManager::class),
                $app->make(FeatureUsageRepository::class),
            );
        });

        $this->app->alias(FeatureLimiterManager::class, 'feature-limiter');
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
