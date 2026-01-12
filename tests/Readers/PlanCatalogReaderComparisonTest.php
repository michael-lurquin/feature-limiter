<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;

class PlanCatalogReaderComparisonTest extends TestCase
{
public function test_it_builds_a_grouped_comparison_table(): void
    {
        Plan::create(['key' => 'free', 'name' => 'Free', 'sort' => 0, 'active' => true]);
        Plan::create(['key' => 'starter', 'name' => 'Starter', 'sort' => 1, 'active' => true]);

        Feature::create([
            'key' => 'sites',
            'name' => 'Sites',
            'type' => FeatureType::INTEGER,
            'group' => 'create-design',
            'sort' => 1,
            'active' => true,
        ]);

        Feature::create([
            'key' => 'custom_code',
            'name' => 'Custom code',
            'type' => FeatureType::BOOLEAN,
            'group' => 'create-design',
            'sort' => 2,
            'active' => true,
        ]);

        FeatureLimiter::grant('free')->features([
            'sites' => 1,
            'custom_code' => false,
        ]);

        FeatureLimiter::grant('starter')->features([
            'sites' => 3,
            'custom_code' => true,
        ]);

        $table = FeatureLimiter::catalog()->comparisonTable();

        $this->assertSame(['free', 'starter'], array_map(fn ($p) => $p['key'], $table['plans']));

        $this->assertCount(1, $table['groups']);
        $this->assertSame('create-design', $table['groups'][0]['key']);

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
