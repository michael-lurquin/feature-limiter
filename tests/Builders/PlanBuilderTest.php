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

        $plan->refresh();
        $this->assertSame(0, $plan->sort);
        $this->assertTrue($plan->active);
    }

    public function test_it_creates_a_plan_with_full_syntax(): void
    {
        $plan = FeatureLimiter::plan('starter')
            ->name('Starter')
            ->sort(12)
            ->active(false)
            ->save();

        $plan->refresh();

        $this->assertSame('starter', $plan->key);
        $this->assertSame('Starter', $plan->name);
        $this->assertSame(12, $plan->sort);
        $this->assertFalse($plan->active);
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
