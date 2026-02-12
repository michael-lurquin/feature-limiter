<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;
use MichaelLurquin\FeatureLimiter\Tests\Concerns\InteractsWithFeatureLimiter;

class PlanCatalogReaderComparisonTest extends TestCase
{
    use InteractsWithFeatureLimiter;

    public function test_it_builds_a_grouped_comparison_table(): void
    {
        $this->flPlan('free', 'Free', 0);

        $this->flPlan('starter', 'Starter', 1, attributes: ['description' => 'To begin with...', 'price_monthly' => 9.99, 'price_yearly' => 129]);

        $this->flFeature('sites', FeatureType::INTEGER, 'Sites', 1, group: 'create-design');
        $this->flFeature('custom_code', FeatureType::BOOLEAN, 'Custom code', 2, group: 'create-design');

        $this->flGrantQuota('free', 'sites', 1);
        $this->flGrantEnabled('free', 'custom_code', false);

        $this->flGrantQuota('starter', 'sites', 3);
        $this->flGrantEnabled('starter', 'custom_code', true);

        $table = FeatureLimiter::catalog()->comparisonTable();

        $this->assertSame(['free', 'starter'], array_map(fn ($p) => $p['key'], $table['plans']));

        $this->assertCount(1, $table['groups']);
        $this->assertSame('create-design', $table['groups'][0]['key']);

        $this->assertNull($table['plans'][0]['description']);
        $this->assertSame('To begin with...', $table['plans'][1]['description']);

        $prices = $table['prices']['starter'];
        $this->assertSame(9.99, $prices['monthly']);
        $this->assertSame(129, $prices['yearly']);

        $features = $table['groups'][0]['features'];
        $this->assertCount(2, $features);

        $sitesRow = $features[0];
        $this->assertSame('sites', $sitesRow['key']);
        $this->assertSame(1, $sitesRow['values']['free']);
        $this->assertSame(3, $sitesRow['values']['starter']);

        $ccRow = $features[1];
        $this->assertSame('custom_code', $ccRow['key']);
        $this->assertFalse($ccRow['values']['free']);
        $this->assertTrue($ccRow['values']['starter']);
    }
}
