<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Builders;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;

class GrantBuilderTest extends TestCase
{
    public function test_it_attaches_limit_to_plan_feature(): void
    {
        $plan = Plan::create(['key' => 'starter', 'name' => 'Starter']);

        $feature = Feature::create([
            'key' => 'sites',
            'name' => 'Sites',
            'type' => FeatureType::INTEGER,
        ]);

        FeatureLimiter::grant('starter')->feature('sites')->quota(3);

        $plan->refresh();

        $attached = $plan->features()->find($feature->id);
        $this->assertNotNull($attached);

        $this->assertSame('3', $attached->planFeature->value);
        $this->assertFalse($attached->planFeature->is_unlimited);
    }

    public function test_it_attaches_unlimited_to_plan_feature(): void
    {
        Plan::create(['key' => 'pro', 'name' => 'Pro']);

        Feature::create([
            'key' => 'storage',
            'name' => 'Storage',
            'type' => FeatureType::STORAGE,
        ]);

        FeatureLimiter::grant('pro')->feature('storage')->unlimited();

        $plan = Plan::where('key', 'pro')->firstOrFail();
        $feature = Feature::where('key', 'storage')->firstOrFail();

        $attached = $plan->features()->find($feature->id);
        $this->assertNull($attached->planFeature->value);
        $this->assertTrue($attached->planFeature->is_unlimited);
    }

    public function test_it_enables_a_boolean_feature(): void
    {
        FeatureLimiter::plan('starter')->name('Starter')->save();

        FeatureLimiter::feature('custom_code')
            ->name('Custom code')
            ->type(FeatureType::BOOLEAN)
            ->save();

        FeatureLimiter::grant('starter')
            ->feature('custom_code')
            ->enabled();

        $feature = FeatureLimiter::viewPlan('starter')
            ->enabled('custom_code');

        $this->assertTrue($feature);
    }

    public function test_it_disables_a_boolean_feature(): void
    {
        FeatureLimiter::plan('starter')->name('Starter')->save();

        FeatureLimiter::feature('library')
            ->name('Image library')
            ->type(FeatureType::BOOLEAN)
            ->save();

        FeatureLimiter::grant('starter')
            ->feature('library')
            ->disabled();

        $feature = FeatureLimiter::viewPlan('starter')
            ->enabled('library');

        $this->assertFalse($feature);
    }

    public function test_it_throws_if_plan_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Feature::create([
            'key' => 'sites',
            'name' => 'Sites',
            'type' => FeatureType::INTEGER,
        ]);

        FeatureLimiter::grant('missing')->feature('sites')->quota(3);
    }

    public function test_it_throws_if_feature_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Plan::create(['key' => 'starter', 'name' => 'Starter']);

        FeatureLimiter::grant('starter')->feature('missing')->quota(3);
    }

    public function test_boolean_feature_cannot_be_unlimited(): void
    {
        FeatureLimiter::plan('starter')->name('Starter')->save();

        FeatureLimiter::feature('custom_code')
            ->name('Custom code')
            ->type(FeatureType::BOOLEAN)
            ->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Boolean feature cannot be unlimited');

        FeatureLimiter::grant('starter')
            ->feature('custom_code')
            ->unlimited();
    }
}
