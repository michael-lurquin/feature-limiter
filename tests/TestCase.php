<?php

namespace MichaelLurquin\FeatureLimiter\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use MichaelLurquin\FeatureLimiter\FeatureLimiterManager;
use MichaelLurquin\FeatureLimiter\FeatureLimiterServiceProvider;
use MichaelLurquin\FeatureLimiter\Tests\Fakes\FakeBillingProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            FeatureLimiterServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('feature-limiter.tables.features', 'fl_features');
        $app['config']->set('feature-limiter.tables.plans', 'fl_plans');
        $app['config']->set('feature-limiter.tables.plan_feature', 'fl_feature_plan');
        $app['config']->set('feature-limiter.tables.usages', 'fl_feature_usages');

        $app['config']->set('feature-limiter.billing.providers.fake', FakeBillingProvider::class);
        $app['config']->set('feature-limiter.billing.default', 'fake');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate')->run();
    }

    public function test_facade_resolves_manager(): void
    {
        $manager = app('feature-limiter');

        $this->assertInstanceOf(FeatureLimiterManager::class, $manager);
    }
}
