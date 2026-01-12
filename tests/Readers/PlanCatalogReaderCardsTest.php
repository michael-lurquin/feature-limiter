<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;
use MichaelLurquin\FeatureLimiter\Tests\Concerns\InteractsWithFeatureLimiter;

class PlanCatalogReaderCardsTest extends TestCase
{
    use InteractsWithFeatureLimiter;

    private function setupPlansAndFeatures(): void
    {
        $this->flPlan('starter', 'Starter', 1);
        $this->flPlan('pro', 'Pro', 2);

        $this->flFeature('sites', FeatureType::INTEGER, 'Sites', 1);
        $this->flFeature('storage', FeatureType::STORAGE, 'Storage', 2);
        $this->flFeature('custom_code', FeatureType::BOOLEAN, 'Custom code', 3);

        $this->flGrantQuota('starter', 'sites', 3);
        $this->flGrantValue('starter', 'storage', '1GB');
        $this->flGrantEnabled('starter', 'custom_code', false);

        $this->flGrantQuota('pro', 'sites', 25);
        $this->flGrantValue('pro', 'storage', '10GB');
        $this->flGrantEnabled('pro', 'custom_code', true);
    }

    public function test_it_returns_plans_cards_with_featured_features(): void
    {
        $this->setupPlansAndFeatures();

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
