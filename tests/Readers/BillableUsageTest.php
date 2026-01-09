<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;
use MichaelLurquin\FeatureLimiter\Tests\Fakes\FakeBillingProvider;

class BillableUsageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeBillingProvider::$resolver = null;
    }

    public function test_it_increments_decrements_and_sets_usage(): void
    {
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        $billable = new class { public int $id = 1; };

        $reader = FeatureLimiter::for($billable);

        $this->assertSame(0, $reader->usage('sites'));

        $this->assertSame(2, $reader->incrementUsage('sites', 2));
        $this->assertSame(2, $reader->usage('sites'));

        $this->assertSame(1, $reader->decrementUsage('sites', 1));
        $this->assertSame(1, $reader->usage('sites'));

        $this->assertSame(10, $reader->setUsage('sites', 10));
        $this->assertSame(10, $reader->usage('sites'));

        $reader->clearUsage('sites');
        $this->assertSame(0, $reader->usage('sites'));
    }

    public function test_usage_defaults_to_zero_when_no_row_exists(): void
    {
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        $billable = new class { public int $id = 1; };

        $reader = FeatureLimiter::for($billable);

        $this->assertSame(0, $reader->usage('sites'));
    }

    public function test_increment_usage_creates_row_and_returns_new_value(): void
    {
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        $billable = new class { public int $id = 1; };

        $reader = FeatureLimiter::for($billable);

        $this->assertSame(2, $reader->incrementUsage('sites', 2));
        $this->assertSame(2, $reader->usage('sites'));

        $this->assertSame(5, $reader->incrementUsage('sites', 3));
        $this->assertSame(5, $reader->usage('sites'));
    }

    public function test_decrement_usage_decreases_and_never_goes_below_zero(): void
    {
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        $billable = new class { public int $id = 1; };

        $reader = FeatureLimiter::for($billable);

        $reader->setUsage('sites', 5);

        $this->assertSame(3, $reader->decrementUsage('sites', 2));
        $this->assertSame(3, $reader->usage('sites'));

        // floor à 0
        $this->assertSame(0, $reader->decrementUsage('sites', 999));
        $this->assertSame(0, $reader->usage('sites'));
    }

    public function test_set_usage_overwrites_value(): void
    {
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        $billable = new class { public int $id = 1; };

        $reader = FeatureLimiter::for($billable);

        $reader->incrementUsage('sites', 2);
        $this->assertSame(2, $reader->usage('sites'));

        $this->assertSame(10, $reader->setUsage('sites', 10));
        $this->assertSame(10, $reader->usage('sites'));

        $this->assertSame(0, $reader->setUsage('sites', 0));
        $this->assertSame(0, $reader->usage('sites'));
    }

    public function test_clear_usage_deletes_row_and_usage_returns_zero_again(): void
    {
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        $billable = new class { public int $id = 1; };

        $reader = FeatureLimiter::for($billable);

        $reader->setUsage('sites', 7);
        $this->assertSame(7, $reader->usage('sites'));

        $reader->clearUsage('sites');
        $this->assertSame(0, $reader->usage('sites'));
    }

    public function test_usage_throws_if_feature_does_not_exist(): void
    {
        $billable = new class { public int $id = 1; };

        $reader = FeatureLimiter::for($billable);

        $this->expectException(InvalidArgumentException::class);
        $reader->usage('unknown_feature');
    }

    public function test_increment_usage_rejects_negative_amount(): void
    {
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        $billable = new class { public int $id = 1; };

        $reader = FeatureLimiter::for($billable);

        $this->expectException(InvalidArgumentException::class);
        $reader->incrementUsage('sites', -1);
    }

    public function test_decrement_usage_rejects_negative_amount(): void
    {
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        $billable = new class { public int $id = 1; };

        $reader = FeatureLimiter::for($billable);

        $this->expectException(InvalidArgumentException::class);
        $reader->decrementUsage('sites', -1);
    }

    public function test_set_usage_rejects_negative_value(): void
    {
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        $billable = new class { public int $id = 1; };

        $reader = FeatureLimiter::for($billable);

        $this->expectException(InvalidArgumentException::class);
        $reader->setUsage('sites', -10);
    }
}
