<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;
use MichaelLurquin\FeatureLimiter\Tests\Concerns\InteractsWithFeatureLimiter;

class PlanFeatureReaderTest extends TestCase
{
    use InteractsWithFeatureLimiter;

    public function test_it_reads_integer_limit(): void
    {
        $this->flPlan('starter');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flGrantQuota('starter', 'sites', 3);

        $this->assertSame(3, FeatureLimiter::viewPlan('starter')->quota('sites'));
    }

    public function test_it_reads_boolean_feature(): void
    {
        $this->flPlan('starter');
        $this->flFeature('custom_code', FeatureType::BOOLEAN);
        $this->flGrantEnabled('starter', 'custom_code', true);

        $this->assertTrue(FeatureLimiter::viewPlan('starter')->enabled('custom_code'));
    }

    public function test_it_detects_unlimited(): void
    {
        $this->flPlan('pro', 'Pro');
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantValue('pro', 'storage', 'unlimited');

        $this->assertTrue(FeatureLimiter::viewPlan('pro')->unlimited('storage'));
    }

    public function test_it_detects_value(): void
    {
        $this->flPlan('pro', 'Pro');
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantValue('pro', 'storage', '1GB');

        $this->assertSame('1GB', FeatureLimiter::viewPlan('pro')->value('storage'));
    }

    public function test_it_reads_boolean_and_integer_values(): void
    {
        $this->flPlan('starter');
        $this->flFeature('custom_code', FeatureType::BOOLEAN);
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flGrantEnabled('starter', 'custom_code', true);
        $this->flGrantQuota('starter', 'sites', 5);

        $reader = FeatureLimiter::viewPlan('starter');

        $this->assertTrue($reader->value('custom_code'));
        $this->assertSame(5, $reader->value('sites'));
    }
}
