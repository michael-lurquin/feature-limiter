<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Console;

use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Models\FeatureUsage;

class PruneFeatureUsagesCommandTest extends TestCase
{
    private function createSitesFeature(): void
    {
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => 'integer']);
    }

    private function createUsage(array $overrides = []): void
    {
        FeatureUsage::create(array_merge([
            'usable_type' => 'User',
            'usable_id' => 1,
            'feature_id' => 1,
            'period_start' => '2020-01-01',
            'period_end' => '2020-01-31',
            'used' => 5,
        ], $overrides));
    }

    public function test_dry_run_does_not_delete_anything(): void
    {
        $this->createSitesFeature();
        $this->createUsage();

        $this->artisan('feature-limiter:prune-usages --years=1 --dry-run')
            ->expectsOutputToContain('Dry run')
            ->assertExitCode(0);

        $this->assertSame(1, FeatureUsage::count());
    }

    public function test_old_usages_are_deleted(): void
    {
        $this->createSitesFeature();

        // Old usage
        $this->createUsage();

        // Recent usage
        $this->createUsage([
            'period_start' => now()->subDays(10),
            'period_end' => now()->subDays(1),
            'used' => 2,
        ]);

        $this->artisan('feature-limiter:prune-usages --years=1')
            ->assertExitCode(0);

        $this->assertSame(1, FeatureUsage::count());
    }

    public function test_prune_zero_usage_option(): void
    {
        $this->createSitesFeature();
        $this->createUsage(['used' => 0]);

        $this->artisan('feature-limiter:prune-usages --years=1 --prune-zero')
            ->assertExitCode(0);

        $this->assertSame(0, FeatureUsage::count());
    }
}
