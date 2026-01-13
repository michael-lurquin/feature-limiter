<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Builders;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;

class FeaturesBuilderTest extends TestCase
{
    public function test_it_creates_multiple_features_from_associative_map(): void
    {
        $features = FeatureLimiter::features([
            'sites' => ['sort' => 0, 'type' => FeatureType::INTEGER],
            'storage' => ['sort' => 1, 'type' => FeatureType::STORAGE],
            'custom_code' => ['name' => 'Custom Code', 'type' => FeatureType::BOOLEAN, 'sort' => 2],
        ])->save();

        $this->assertIsArray($features);
        $this->assertArrayHasKey('sites', $features);
        $this->assertArrayHasKey('custom_code', $features);

        $sites = Feature::query()->where('key', 'sites')->firstOrFail();
        $this->assertSame('Sites', $sites->name);
        $this->assertSame(0, $sites->sort);
        $this->assertSame(FeatureType::INTEGER, $sites->type);

        $storage = Feature::query()->where('key', 'storage')->firstOrFail();
        $this->assertSame('Storage', $storage->name);
        $this->assertSame(1, $storage->sort);
        $this->assertSame(FeatureType::STORAGE, $storage->type);

        $custom = Feature::query()->where('key', 'custom_code')->firstOrFail();
        $this->assertSame('Custom Code', $custom->name);
        $this->assertSame(2, $custom->sort);
        $this->assertSame(FeatureType::BOOLEAN, $custom->type);
    }

    public function test_it_creates_multiple_features_short_version_and_auto_sets_sort_and_default_name(): void
    {
        FeatureLimiter::features([
            'sites' => ['type' => FeatureType::INTEGER],
            'storage' => ['type' => FeatureType::STORAGE],
            'custom_code' => ['name' => 'Custom Code', 'type' => FeatureType::BOOLEAN],
        ])->save();

        $sites = Feature::query()->where('key', 'sites')->firstOrFail();
        $this->assertSame('Sites', $sites->name);
        $this->assertSame(0, $sites->sort);
        $this->assertSame(FeatureType::INTEGER, $sites->type);

        $storage = Feature::query()->where('key', 'storage')->firstOrFail();
        $this->assertSame('Storage', $storage->name);
        $this->assertSame(1, $storage->sort);
        $this->assertSame(FeatureType::STORAGE, $storage->type);

        $custom = Feature::query()->where('key', 'custom_code')->firstOrFail();
        $this->assertSame('Custom Code', $custom->name);
        $this->assertSame(2, $custom->sort);
        $this->assertSame(FeatureType::BOOLEAN, $custom->type);
    }

    public function test_it_updates_existing_features_instead_of_creating_duplicates(): void
    {
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER, 'sort' => 0]);

        FeatureLimiter::features([
            'sites' => ['name' => 'Websites', 'type' => FeatureType::INTEGER, 'sort' => 10],
        ])->save();

        $sites = Feature::query()->where('key', 'sites')->firstOrFail();
        $this->assertSame('Websites', $sites->name);
        $this->assertSame(10, $sites->sort);
        $this->assertSame(FeatureType::INTEGER, $sites->type);

        $this->assertSame(1, Feature::query()->where('key', 'sites')->count());
    }

    public function test_it_requires_attributes_array_per_feature_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // since you require an attributes sub-array per feature key
        FeatureLimiter::features([
            'sites' => FeatureType::INTEGER, // invalid: not an array
        ])->save();
    }
}
