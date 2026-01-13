<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Builders;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;

class PlansBuilderTest extends TestCase
{
    public function test_it_creates_multiple_plans_from_associative_map(): void
    {
        $plans = FeatureLimiter::plans([
            'free' => ['sort' => 0],
            'starter' => ['sort' => 1],
            'comfort' => ['sort' => 2],
            'pro' => ['name' => 'Gold', 'sort' => 3],
            'enterprise' => ['sort' => 4, 'active' => false],
        ])->save();

        $this->assertIsArray($plans);
        $this->assertArrayHasKey('free', $plans);
        $this->assertArrayHasKey('pro', $plans);

        $free = Plan::query()->where('key', 'free')->firstOrFail();
        $this->assertSame('Free', $free->name);
        $this->assertSame(0, $free->sort);
        $this->assertTrue((bool) $free->active);

        $pro = Plan::query()->where('key', 'pro')->firstOrFail();
        $this->assertSame('Gold', $pro->name);
        $this->assertSame(3, $pro->sort);

        $enterprise = Plan::query()->where('key', 'enterprise')->firstOrFail();
        $this->assertSame('Enterprise', $enterprise->name);
        $this->assertSame(4, $enterprise->sort);
        $this->assertFalse((bool) $enterprise->active);
    }

    public function test_it_creates_multiple_plans_from_list_syntax_and_auto_sets_sort_and_name(): void
    {
        FeatureLimiter::plans([
            'free',
            'starter',
            'comfort',
            'pro',
            'enterprise',
        ])->save();

        $free = Plan::query()->where('key', 'free')->firstOrFail();
        $this->assertSame('Free', $free->name);
        $this->assertSame(0, $free->sort);

        $starter = Plan::query()->where('key', 'starter')->firstOrFail();
        $this->assertSame('Starter', $starter->name);
        $this->assertSame(1, $starter->sort);

        $enterprise = Plan::query()->where('key', 'enterprise')->firstOrFail();
        $this->assertSame('Enterprise', $enterprise->name);
        $this->assertSame(4, $enterprise->sort);
    }

    public function test_it_updates_existing_plans_instead_of_creating_duplicates(): void
    {
        Plan::create(['key' => 'pro', 'name' => 'Pro', 'sort' => 0, 'active' => true]);

        FeatureLimiter::plans([
            'pro' => ['name' => 'Gold', 'sort' => 3],
        ])->save();

        $pro = Plan::query()->where('key', 'pro')->firstOrFail();
        $this->assertSame('Gold', $pro->name);
        $this->assertSame(3, $pro->sort);

        $this->assertSame(1, Plan::query()->where('key', 'pro')->count());
    }
}
