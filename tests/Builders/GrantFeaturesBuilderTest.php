<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Builders;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;

class GrantFeaturesBuilderTest extends TestCase
{
    public function test_it_grants_multiple_features_in_one_call(): void
    {
        Plan::create(['key' => 'starter', 'name' => 'Starter']);

        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);
        Feature::create(['key' => 'page', 'name' => 'Pages', 'type' => FeatureType::INTEGER]);
        Feature::create(['key' => 'custom_code', 'name' => 'Custom code', 'type' => FeatureType::BOOLEAN]);
        Feature::create(['key' => 'storage', 'name' => 'Storage', 'type' => FeatureType::STORAGE]);

        $plan = FeatureLimiter::grant('starter')->features([
            'sites' => 3,
            'page' => 30,
            'custom_code' => false,
            'storage' => '1GB',
        ]);

        $sites = $plan->features()->where('key', 'sites')->firstOrFail();
        $this->assertSame('3', $sites->planFeature->value);
        $this->assertFalse($sites->planFeature->is_unlimited);

        $custom = $plan->features()->where('key', 'custom_code')->firstOrFail();
        $this->assertSame('0', $custom->planFeature->value);

        $storage = $plan->features()->where('key', 'storage')->firstOrFail();
        $this->assertSame('1GB', $storage->planFeature->value);
    }

    public function test_null_means_unlimited_for_integer_or_storage(): void
    {
        Plan::create(['key' => 'pro', 'name' => 'Pro']);

        Feature::create(['key' => 'storage', 'name' => 'Storage', 'type' => FeatureType::STORAGE]);

        $plan = FeatureLimiter::grant('pro')->features(['storage' => null]);

        $storage = $plan->features()->where('key', 'storage')->firstOrFail();
        $this->assertNull($storage->planFeature->value);
        $this->assertTrue($storage->planFeature->is_unlimited);
    }

    public function test_boolean_cannot_be_unlimited(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Plan::create(['key' => 'starter', 'name' => 'Starter']);

        Feature::create(['key' => 'custom_code', 'name' => 'Custom', 'type' => FeatureType::BOOLEAN]);

        FeatureLimiter::grant('starter')->features(['custom_code' => null]);
    }

    public function test_string_unlimited_sets_is_unlimited(): void
    {
        Plan::create(['key' => 'pro', 'name' => 'Pro']);

        Feature::create([
            'key' => 'storage',
            'name' => 'Storage',
            'type' => FeatureType::STORAGE,
        ]);

        $plan = FeatureLimiter::grant('pro')->features([
            'storage' => 'unlimited',
        ]);

        $storage = $plan->features()->where('key', 'storage')->firstOrFail();

        $this->assertNull($storage->planFeature->value);
        $this->assertTrue($storage->planFeature->is_unlimited);
    }
}
