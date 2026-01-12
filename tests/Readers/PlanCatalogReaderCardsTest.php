<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;

class PlanCatalogReaderCardsTest extends TestCase
{
    public function test_it_returns_plans_cards_with_featured_features(): void
    {
        Plan::create(['key' => 'starter', 'name' => 'Starter', 'sort' => 1, 'active' => true]);
        Plan::create(['key' => 'pro', 'name' => 'Pro', 'sort' => 2, 'active' => true]);

        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER, 'sort' => 1, 'active' => true]);
        Feature::create(['key' => 'storage', 'name' => 'Storage', 'type' => FeatureType::STORAGE, 'sort' => 2, 'active' => true]);
        Feature::create(['key' => 'custom_code', 'name' => 'Custom code', 'type' => FeatureType::BOOLEAN, 'sort' => 3, 'active' => true]);

        FeatureLimiter::grant('starter')->features([
            'sites' => 3,
            'storage' => '1GB',
            'custom_code' => false,
        ]);

        FeatureLimiter::grant('pro')->features([
            'sites' => 25,
            'storage' => '10GB',
            'custom_code' => true,
        ]);

        $cards = FeatureLimiter::catalog()->plansCards(featured: ['sites', 'storage', 'custom_code']);

        $this->assertCount(2, $cards);

        $this->assertSame('starter', $cards[0]['key']);
        $this->assertSame(3, $cards[0]['featured'][0]['value']); // sites
        $this->assertSame('1GB', $cards[0]['featured'][1]['value']); // storage
        $this->assertFalse($cards[0]['featured'][2]['value']); // custom_code

        $this->assertSame('pro', $cards[1]['key']);
        $this->assertSame(25, $cards[1]['featured'][0]['value']);
        $this->assertSame('10GB', $cards[1]['featured'][1]['value']);
        $this->assertTrue($cards[1]['featured'][2]['value']);
    }
}
