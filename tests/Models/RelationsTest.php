<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Models;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;

class RelationsTest extends TestCase
{
    public function test_plan_can_attach_features_with_pivot_values(): void
    {
        $plan = Plan::create([
            'key' => 'starter',
            'name' => 'Starter',
            'sort' => 1,
            'active' => true,
        ]);

        $feature = Feature::create([
            'key' => 'sites',
            'name' => 'Sites',
            'group' => 'creation-design',
            'type' => FeatureType::INTEGER,
            'unit' => 'sites',
            'reset_period' => 'none',
            'sort' => 1,
            'active' => true,
        ]);

        $plan->features()->attach($feature->id, [
            'value' => '3',
            'is_unlimited' => false,
        ]);

        $plan->refresh();
        $this->assertCount(1, $plan->features);

        $f = $plan->features->first();
        $this->assertSame('sites', $f->key);
        $this->assertSame('3', $f->planFeature->value);
        $this->assertFalse($f->planFeature->is_unlimited);
    }

    public function test_feature_knows_its_plans(): void
    {
        $plan = Plan::create(['key' => 'pro', 'name' => 'Pro', 'sort' => 2, 'active' => true]);

        $feature = Feature::create([
            'key' => 'custom_code',
            'name' => 'Custom code',
            'type' => FeatureType::BOOLEAN,
            'reset_period' => 'none',
            'sort' => 10,
            'active' => true,
        ]);

        $feature->plans()->attach($plan->id, [
            'value' => '1',
            'is_unlimited' => false,
        ]);

        $feature->refresh();
        $this->assertCount(1, $feature->plans);
        $this->assertSame('pro', $feature->plans->first()->key);
    }
}
