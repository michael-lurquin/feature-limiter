<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Builders;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;

class PlanBuilderTest extends TestCase
{
    public function test_it_creates_a_plan_with_minimal_syntax(): void
    {
        $plan = FeatureLimiter::plan('starter')
            ->name('Starter') // Optional
            ->save();

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertSame('starter', $plan->key);
        $this->assertSame('Starter', $plan->name);
        $this->assertNull($plan->description);

        $plan->refresh();
        $this->assertSame(0, $plan->sort);
        $this->assertTrue($plan->active);
    }

    public function test_it_creates_a_plan_with_prices(): void
    {
        $plan = FeatureLimiter::plan('starter')
            ->name('Starter') // Optional
            ->monthly(9.99)
            ->yearly(129)
            ->save();

        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertSame('starter', $plan->key);
        $this->assertSame('Starter', $plan->name);
        $this->assertSame(9.99, $plan->price_monthly);
        $this->assertSame(129, $plan->price_yearly);
        $this->assertSame(999, $plan->getRawOriginal('price_monthly'));
        $this->assertSame(12900, $plan->getRawOriginal('price_yearly'));
    }

    public function test_it_reads_prices_as_decimals(): void
    {
        $plan = Plan::query()->create([
            'key' => 'starter',
            'name' => 'Starter',
            'price_monthly' => 9.99,
            'price_yearly' => 129,
        ]);

        $this->assertSame(9.99, $plan->price_monthly);
        $this->assertSame(129, $plan->price_yearly);
        $this->assertSame(999, $plan->getRawOriginal('price_monthly'));
        $this->assertSame(12900, $plan->getRawOriginal('price_yearly'));
        $this->assertEqualsWithDelta(9.99, $plan->price_monthly, 0.00001);
        $this->assertEqualsWithDelta(129.00, $plan->price_yearly, 0.00001);
    }

    public function test_it_allows_null_prices(): void
    {
        $plan = FeatureLimiter::plan('free')
            ->name('Free')
            ->monthly(null)
            ->yearly(null)
            ->save();

        $this->assertNull($plan->price_monthly);
        $this->assertNull($plan->price_yearly);
        $this->assertNull($plan->getRawOriginal('price_monthly'));
        $this->assertNull($plan->getRawOriginal('price_yearly'));
    }

    public function test_it_creates_a_plan_with_full_syntax(): void
    {
        $plan = FeatureLimiter::plan('starter')
            ->name('Starter')
            ->description('To begin with...')
            ->sort(12)
            ->active(false)
            ->save();

        $plan->refresh();

        $this->assertSame('starter', $plan->key);
        $this->assertSame('Starter', $plan->name);
        $this->assertSame('To begin with...', $plan->description);
        $this->assertSame(12, $plan->sort);
        $this->assertFalse($plan->active);
        $this->assertNull($plan->price_monthly);
        $this->assertNull($plan->price_yearly);
    }

    public function test_save_updates_existing_plan_only_for_provided_attributes(): void
    {
        $existing = Plan::create([
            'key' => 'starter',
            'name' => 'Old name',
            'sort' => 99,
            'active' => false,
        ]);

        $updated = FeatureLimiter::plan('starter')
            ->name('Starter')
            ->save();

        $updated->refresh();

        $this->assertSame($existing->id, $updated->id);
        $this->assertSame('Starter', $updated->name);

        $this->assertSame(99, $updated->sort);
        $this->assertFalse($updated->active);
    }

    public function test_save_updates_sort_and_active_when_provided(): void
    {
        Plan::create([
            'key' => 'starter',
            'name' => 'Starter',
            'sort' => 0,
            'active' => true,
        ]);

        $plan = FeatureLimiter::plan('starter')
            ->sort(5)
            ->active(false)
            ->save();

        $plan->refresh();

        $this->assertSame(5, $plan->sort);
        $this->assertFalse($plan->active);
    }
}
