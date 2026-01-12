<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Tests\Fakes\AltBillingProvider;
use MichaelLurquin\FeatureLimiter\Tests\Fakes\FakeBillingProvider;
use MichaelLurquin\FeatureLimiter\Tests\Concerns\InteractsWithFeatureLimiter;

class BillableFeatureReaderTest extends TestCase
{
    use InteractsWithFeatureLimiter;

    private function starterReader(array $overrides = [])
    {
        $features = array_replace_recursive([
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
            'custom_code' => ['type' => FeatureType::BOOLEAN, 'enabled' => false],
        ], $overrides);

        $this->flPlan('starter');

        foreach ($features as $key => $def)
        {
            $type = $def['type'] ?? null;

            if ( !$type instanceof FeatureType )
            {
                throw new \InvalidArgumentException("Invalid feature definition for {$key}");
            }

            $this->flFeature($key, $type);

            if ( $type === FeatureType::INTEGER && array_key_exists('quota', $def) )
            {
                $this->flGrantQuota('starter', $key, (int) $def['quota']);
            }
            elseif ( $type === FeatureType::BOOLEAN && array_key_exists('enabled', $def) )
            {
                $this->flGrantEnabled('starter', $key, (bool) $def['enabled']);
            }
            elseif ( array_key_exists('value', $def) )
            {
                $this->flGrantValue('starter', $key, $def['value']);
            }
        }

        $billable = $this->flBillable(123);
        $this->flResolvePlan('starter');

        return $this->flReader($billable);
    }

    public function test_it_resolves_plan_and_reads_features_for_any_billable_object(): void
    {
        $reader = $this->starterReader();

        $this->assertSame(3, $reader->quota('sites'));
        $this->assertFalse($reader->enabled('custom_code'));
    }

    public function test_it_reads_value_for_boolean_and_integer_features(): void
    {
        $reader = $this->starterReader([
            'sites' => ['quota' => 2],
        ]);

        $this->assertSame(2, $reader->value('sites'));
        $this->assertFalse($reader->value('custom_code'));
    }

    public function test_using_selects_an_explicit_provider(): void
    {
        $this->flPlan('starter');
        $this->flPlan('pro', 'Pro');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flGrantQuota('starter', 'sites', 1);
        $this->flGrantQuota('pro', 'sites', 5);

        $billable = $this->flBillable(123);

        FakeBillingProvider::$resolver = fn () => Plan::where('key', 'starter')->first();
        AltBillingProvider::$resolver = fn () => Plan::where('key', 'pro')->first();

        app('config')->set('feature-limiter.billing.providers.alt', AltBillingProvider::class);

        $reader = $this->flReader($billable);

        $this->assertSame(1, $reader->quota('sites'));
        $this->assertSame(5, $reader->using('alt')->quota('sites'));
    }

    public function test_it_throws_when_no_plan_is_resolved(): void
    {
        $billable = $this->flBillable(123);

        FakeBillingProvider::$resolver = fn () => null;

        $reader = $this->flReader($billable);

        $this->expectException(InvalidArgumentException::class);

        $reader->quota('sites');
    }
}
