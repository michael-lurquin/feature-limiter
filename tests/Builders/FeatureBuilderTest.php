<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Builders;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Enums\ResetPeriod;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;

class FeatureBuilderTest extends TestCase
{
    public function test_it_creates_a_feature_with_short_syntax(): void
    {
        $feature = FeatureLimiter::feature('sites')
            ->name('Sites')
            ->type(FeatureType::INTEGER)
            ->save();

        $this->assertInstanceOf(Feature::class, $feature);
        $this->assertSame('sites', $feature->key);
        $this->assertSame('Sites', $feature->name);

        $feature->refresh();

        $this->assertSame(0, $feature->sort);
        $this->assertTrue($feature->active);
        $this->assertSame(ResetPeriod::NONE, $feature->reset_period);

        $this->assertSame(FeatureType::INTEGER, $feature->type);
    }

    public function test_it_creates_a_feature_with_full_syntax(): void
    {
        $feature = FeatureLimiter::feature('sites')
            ->name('Sites')
            ->description('Number of sites you can create')
            ->group('create-design')
            ->type(FeatureType::INTEGER)
            ->unit('sites')
            ->reset(ResetPeriod::MONTHLY)
            ->sort(10)
            ->active(false)
            ->save();

        $feature->refresh();

        $this->assertSame('sites', $feature->key);
        $this->assertSame('Sites', $feature->name);
        $this->assertSame('Number of sites you can create', $feature->description);
        $this->assertSame('create-design', $feature->group);
        $this->assertSame(FeatureType::INTEGER, $feature->type);
        $this->assertSame('sites', $feature->unit);
        $this->assertSame(ResetPeriod::MONTHLY, $feature->reset_period);
        $this->assertSame(10, $feature->sort);
        $this->assertFalse($feature->active);
    }

    public function test_save_updates_existing_feature_only_for_provided_attributes(): void
    {
        Feature::create([
            'key' => 'sites',
            'name' => 'Old',
            'description' => 'Old desc',
            'group' => 'old-group',
            'type' => FeatureType::BOOLEAN,
            'unit' => 'old-unit',
            'reset_period' => ResetPeriod::YEARLY,
            'sort' => 99,
            'active' => false,
        ]);

        $feature = FeatureLimiter::feature('sites')
            ->name('Sites')
            ->type(FeatureType::INTEGER)
            ->save();

        $feature->refresh();

        $this->assertSame('Sites', $feature->name);
        $this->assertSame(FeatureType::INTEGER, $feature->type);

        $this->assertSame('Old desc', $feature->description);
        $this->assertSame('old-group', $feature->group);
        $this->assertSame('old-unit', $feature->unit);
        $this->assertSame(ResetPeriod::YEARLY, $feature->reset_period);
        $this->assertSame(99, $feature->sort);
        $this->assertFalse($feature->active);
    }

    public function test_type_accepts_string_and_casts_to_enum(): void
    {
        $feature = FeatureLimiter::feature('custom_code')
            ->name('Custom code')
            ->type('boolean')
            ->save();

        $feature->refresh();

        $this->assertSame('custom_code', $feature->key);
        $this->assertSame(FeatureType::BOOLEAN, $feature->type);
    }

    public function test_reset_rejects_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FeatureLimiter::feature('sites')
            ->name('Sites')
            ->type(FeatureType::INTEGER)
            ->reset('weekly') // invalid
            ->save();
    }

    public function test_type_rejects_invalid_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FeatureLimiter::feature('sites')
            ->name('Sites')
            ->type('json') // invalid
            ->save();
    }
}
