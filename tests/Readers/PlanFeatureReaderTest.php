<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;

class PlanFeatureReaderTest extends TestCase
{
    public function test_it_reads_integer_limit(): void
    {
        Plan::create(['key' => 'starter', 'name' => 'Starter']);

        Feature::create([
            'key' => 'sites',
            'name' => 'Sites',
            'type' => FeatureType::INTEGER,
        ]);

        FeatureLimiter::grant('starter')->feature('sites')->quota(3);

        $this->assertSame(3, FeatureLimiter::viewPlan('starter')->quota('sites'));
    }

    public function test_it_reads_boolean_feature(): void
    {
        Plan::create(['key' => 'starter', 'name' => 'Starter']);

        Feature::create([
            'key' => 'custom_code',
            'name' => 'Custom code',
            'type' => FeatureType::BOOLEAN,
        ]);

        FeatureLimiter::grant('starter')->feature('custom_code')->enabled();

        $this->assertTrue(FeatureLimiter::viewPlan('starter')->enabled('custom_code'));
    }

    public function test_it_detects_unlimited(): void
    {
        Plan::create(['key' => 'pro', 'name' => 'Pro']);

        Feature::create([
            'key' => 'storage',
            'name' => 'Storage',
            'type' => FeatureType::STORAGE,
        ]);

        FeatureLimiter::grant('pro')->feature('storage')->unlimited();

        $this->assertTrue(FeatureLimiter::viewPlan('pro')->unlimited('storage'));
    }

    public function test_it_detects_value(): void
    {
        Plan::create(['key' => 'pro', 'name' => 'Pro']);

        Feature::create([
            'key' => 'storage',
            'name' => 'Storage',
            'type' => FeatureType::STORAGE,
        ]);

        FeatureLimiter::grant('pro')->feature('storage')->value('1GB');

        $this->assertSame('1GB', FeatureLimiter::viewPlan('pro')->value('storage'));
    }
}
