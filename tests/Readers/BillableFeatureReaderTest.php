<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;
use MichaelLurquin\FeatureLimiter\Tests\Fakes\FakeBillingProvider;

class BillableFeatureReaderTest extends TestCase
{
    public function test_it_resolves_plan_and_reads_features_for_any_billable_object(): void
    {
        $plan = Plan::create(['key' => 'starter', 'name' => 'Starter']);

        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);
        Feature::create(['key' => 'custom_code', 'name' => 'Custom code', 'type' => FeatureType::BOOLEAN]);

        FeatureLimiter::grant('starter')->features([
            'sites' => 3,
            'custom_code' => false,
        ]);

        $billable = new class {
            public int $id = 123;
        };

        FakeBillingProvider::$resolver = fn ($b) => $plan;

        $reader = FeatureLimiter::for($billable);

        $this->assertSame(3, $reader->quota('sites'));
        $this->assertFalse($reader->enabled('custom_code'));
    }
}
