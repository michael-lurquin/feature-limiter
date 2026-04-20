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

    public function test_it_returns_prices_from_billing_provider(): void
    {
        // Setup plan with provider IDs for testing
        $plan = \MichaelLurquin\FeatureLimiter\Models\Plan::create([
            'key' => 'pro',
            'name' => 'Pro',
            'provider' => 'fake',
            'provider_monthly_id' => 'price_monthly_123',
            'provider_yearly_id' => 'price_yearly_456',
        ]);

        // Mock the FakeBillingProvider to return specific prices
        \MichaelLurquin\FeatureLimiter\Tests\Fakes\FakeBillingProvider::$pricesResolver = function (\MichaelLurquin\FeatureLimiter\Models\Plan $p) {
            if ($p->key === 'pro') {
                return [
                    'monthly' => ['unit_amount' => 2999, 'currency' => 'USD', 'interval' => 'month'],
                    'yearly' => ['unit_amount' => 29900, 'currency' => 'USD', 'interval' => 'year'],
                ];
            }
            return [];
        };

        $reader = FeatureLimiter::viewPlan('pro');
        $prices = $reader->prices();

        $this->assertIsArray($prices);
        $this->assertArrayHasKey('monthly', $prices);
        $this->assertArrayHasKey('yearly', $prices);
        $this->assertSame(2999, $prices['monthly']['unit_amount']);
        $this->assertSame(29900, $prices['yearly']['unit_amount']);
    }
}
