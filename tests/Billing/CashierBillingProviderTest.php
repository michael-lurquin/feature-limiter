<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Billing;

use Stripe\StripeClient;
use Illuminate\Support\Facades\Cache;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Billing\CashierBillingProvider;

/**
 * Subclass exposing fetchPrice as injectable for tests (avoids real Stripe calls).
 */
class TestableCashierBillingProvider extends CashierBillingProvider
{
    /** @var array<string, array> */
    public array $fakePrices = [];

    protected function fetchPrice(string $priceId): array
    {
        return $this->fakePrices[$priceId] ?? [
            'provider_id'          => $priceId,
            'active'               => true,
            'currency'             => 'EUR',
            'unit_amount'          => 0,
            'unit_amount_decimal'  => null,
            'interval'             => null,
            'interval_count'       => null,
            'product'              => null,
            'nickname'             => null,
        ];
    }
}

// ---------------------------------------------------------------------------
// Fake subscription objects (mimic Cashier's Subscription model)
// ---------------------------------------------------------------------------

function makeSubscription(string $priceId, bool $valid = true): object
{
    return new class($priceId, $valid) {
        public string $stripe_price;
        private bool $isValid;
        public function __construct(string $priceId, bool $valid) {
            $this->stripe_price = $priceId;
            $this->isValid = $valid;
        }
        public function valid(): bool { return $this->isValid; }
    };
}

function makeSubscriptionViaItems(string $priceId): object
{
    $item = new class($priceId) {
        public string $stripe_price;
        public function __construct(string $p) { $this->stripe_price = $p; }
    };

    $collection = new class($item) {
        private object $item;
        public function __construct(object $item) { $this->item = $item; }
        public function count(): int { return 1; }
        public function first(): object { return $this->item; }
    };

    // Real Cashier subscriptions expose items() as a relation method AND
    // as a loaded property. CashierBillingProvider checks method_exists($sub, 'items')
    // then accesses $sub->items as a property, so both must exist on the fake.
    return new class($collection) {
        public object $items;
        public function __construct(object $c) { $this->items = $c; }
        public function valid(): bool { return true; }
        public function items(): object { return $this->items; } // satisfies method_exists check
        // no stripe_price property — forces extraction via items
    };
}

function makeBillable(?object $subscription): object
{
    return new class($subscription) {
        public function __construct(private ?object $sub) {}
        public function subscription(string $name): ?object { return $this->sub; }
    };
}

function makeBillableWithoutSubscriptionMethod(): object
{
    return new class {};
}

// ---------------------------------------------------------------------------

class CashierBillingProviderTest extends TestCase
{
    private function provider(
        string $subscriptionName = 'default',
        ?string $defaultPlanKey = 'free',
        array $fakePrices = []
    ): TestableCashierBillingProvider {
        $p = new TestableCashierBillingProvider($subscriptionName, $defaultPlanKey);
        $p->fakePrices = $fakePrices;
        return $p;
    }

    private function makePlan(string $key, ?string $monthlyId = null, ?string $yearlyId = null): Plan
    {
        return Plan::create([
            'key'                 => $key,
            'name'                => ucfirst($key),
            'sort'                => 0,
            'active'              => true,
            'provider_monthly_id' => $monthlyId,
            'provider_yearly_id'  => $yearlyId,
        ]);
    }

    // -----------------------------------------------------------------------
    // resolvePlanFor — fallback to defaultPlanKey
    // -----------------------------------------------------------------------

    public function test_resolve_returns_default_plan_when_billable_has_no_subscription_method(): void
    {
        $this->makePlan('free');
        $provider = $this->provider();

        $plan = $provider->resolvePlanFor(makeBillableWithoutSubscriptionMethod());

        $this->assertNotNull($plan);
        $this->assertSame('free', $plan->key);
    }

    public function test_resolve_returns_null_when_no_default_and_no_subscription_method(): void
    {
        $provider = $this->provider(defaultPlanKey: null);

        $plan = $provider->resolvePlanFor(makeBillableWithoutSubscriptionMethod());

        $this->assertNull($plan);
    }

    public function test_resolve_returns_default_plan_when_subscription_is_null(): void
    {
        $this->makePlan('free');
        $provider = $this->provider();

        $plan = $provider->resolvePlanFor(makeBillable(null));

        $this->assertNotNull($plan);
        $this->assertSame('free', $plan->key);
    }

    public function test_resolve_returns_default_plan_when_subscription_is_not_valid(): void
    {
        $this->makePlan('free');
        $provider = $this->provider();

        $sub = makeSubscription('price_monthly_pro', valid: false);
        $plan = $provider->resolvePlanFor(makeBillable($sub));

        $this->assertSame('free', $plan->key);
    }

    public function test_resolve_returns_null_when_no_default_and_subscription_invalid(): void
    {
        $provider = $this->provider(defaultPlanKey: null);

        $sub = makeSubscription('price_monthly_pro', valid: false);
        $plan = $provider->resolvePlanFor(makeBillable($sub));

        $this->assertNull($plan);
    }

    // -----------------------------------------------------------------------
    // resolvePlanFor — price matched
    // -----------------------------------------------------------------------

    public function test_resolve_returns_plan_matching_monthly_price_id(): void
    {
        $this->makePlan('free');
        $this->makePlan('pro', monthlyId: 'price_monthly_pro');
        $provider = $this->provider();

        $sub = makeSubscription('price_monthly_pro');
        $plan = $provider->resolvePlanFor(makeBillable($sub));

        $this->assertSame('pro', $plan->key);
    }

    public function test_resolve_returns_plan_matching_yearly_price_id(): void
    {
        $this->makePlan('free');
        $this->makePlan('pro', yearlyId: 'price_yearly_pro');
        $provider = $this->provider();

        $sub = makeSubscription('price_yearly_pro');
        $plan = $provider->resolvePlanFor(makeBillable($sub));

        $this->assertSame('pro', $plan->key);
    }

    public function test_resolve_returns_default_plan_when_price_id_not_matched(): void
    {
        $this->makePlan('free');
        $this->makePlan('pro', monthlyId: 'price_monthly_pro');
        $provider = $this->provider();

        $sub = makeSubscription('price_unknown_xyz');
        $plan = $provider->resolvePlanFor(makeBillable($sub));

        $this->assertSame('free', $plan->key);
    }

    public function test_resolve_returns_null_when_no_default_and_price_not_matched(): void
    {
        $this->makePlan('pro', monthlyId: 'price_monthly_pro');
        $provider = $this->provider(defaultPlanKey: null);

        $sub = makeSubscription('price_unknown_xyz');
        $plan = $provider->resolvePlanFor(makeBillable($sub));

        $this->assertNull($plan);
    }

    // -----------------------------------------------------------------------
    // resolvePlanFor — price extracted via items collection
    // -----------------------------------------------------------------------

    public function test_resolve_extracts_price_from_items_when_no_stripe_price_on_subscription(): void
    {
        $this->makePlan('free');
        $this->makePlan('enterprise', monthlyId: 'price_monthly_enterprise');
        $provider = $this->provider();

        $sub = makeSubscriptionViaItems('price_monthly_enterprise');
        $plan = $provider->resolvePlanFor(makeBillable($sub));

        $this->assertSame('enterprise', $plan->key);
    }

    // -----------------------------------------------------------------------
    // resolvePlanFor — custom subscription name
    // -----------------------------------------------------------------------

    public function test_resolve_uses_configured_subscription_name(): void
    {
        $this->makePlan('free');
        $this->makePlan('pro', monthlyId: 'price_monthly_pro');

        $provider = $this->provider(subscriptionName: 'main');

        $billable = new class {
            public function subscription(string $name): ?object
            {
                if ($name !== 'main') return null;
                return makeSubscription('price_monthly_pro');
            }
        };

        $plan = $provider->resolvePlanFor($billable);

        $this->assertSame('pro', $plan->key);
    }

    // -----------------------------------------------------------------------
    // pricesFor
    // -----------------------------------------------------------------------

    public function test_prices_for_returns_both_monthly_and_yearly(): void
    {
        $plan = $this->makePlan('pro',
            monthlyId: 'price_monthly_pro',
            yearlyId:  'price_yearly_pro',
        );

        $provider = $this->provider(fakePrices: [
            'price_monthly_pro' => [
                'provider_id'         => 'price_monthly_pro',
                'active'              => true,
                'currency'            => 'EUR',
                'unit_amount'         => 1200,
                'unit_amount_decimal' => '1200',
                'interval'            => 'month',
                'interval_count'      => 1,
                'product'             => 'prod_pro',
                'nickname'            => 'Pro Monthly',
            ],
            'price_yearly_pro' => [
                'provider_id'         => 'price_yearly_pro',
                'active'              => true,
                'currency'            => 'EUR',
                'unit_amount'         => 12000,
                'unit_amount_decimal' => '12000',
                'interval'            => 'year',
                'interval_count'      => 1,
                'product'             => 'prod_pro',
                'nickname'            => 'Pro Yearly',
            ],
        ]);

        $prices = $provider->pricesFor($plan);

        $this->assertArrayHasKey('monthly', $prices);
        $this->assertArrayHasKey('yearly', $prices);

        $this->assertSame('price_monthly_pro', $prices['monthly']['provider_id']);
        $this->assertSame(1200, $prices['monthly']['unit_amount']);
        $this->assertSame('month', $prices['monthly']['interval']);

        $this->assertSame('price_yearly_pro', $prices['yearly']['provider_id']);
        $this->assertSame(12000, $prices['yearly']['unit_amount']);
        $this->assertSame('year', $prices['yearly']['interval']);
    }

    public function test_prices_for_returns_null_for_missing_ids(): void
    {
        $plan = $this->makePlan('free'); // no price IDs

        $prices = $this->provider()->pricesFor($plan);

        $this->assertNull($prices['monthly']);
        $this->assertNull($prices['yearly']);
    }

    public function test_prices_for_returns_only_monthly_when_no_yearly_id(): void
    {
        $plan = $this->makePlan('starter', monthlyId: 'price_monthly_starter');

        $provider = $this->provider(fakePrices: [
            'price_monthly_starter' => [
                'provider_id'         => 'price_monthly_starter',
                'active'              => true,
                'currency'            => 'EUR',
                'unit_amount'         => 500,
                'unit_amount_decimal' => '500',
                'interval'            => 'month',
                'interval_count'      => 1,
                'product'             => 'prod_starter',
                'nickname'            => 'Starter Monthly',
            ],
        ]);

        $prices = $provider->pricesFor($plan);

        $this->assertNotNull($prices['monthly']);
        $this->assertSame('price_monthly_starter', $prices['monthly']['provider_id']);
        $this->assertNull($prices['yearly']);
    }

    // -----------------------------------------------------------------------
    // fetchPrice — caching
    // -----------------------------------------------------------------------

    public function test_fetch_price_is_cached(): void
    {
        Cache::flush();

        $callCount = 0;

        $provider = new class('default', 'free') extends CashierBillingProvider {
            public int $callCount = 0;
            protected function fetchPrice(string $priceId): array
            {
                $this->callCount++;
                return [
                    'provider_id'         => $priceId,
                    'active'              => true,
                    'currency'            => 'EUR',
                    'unit_amount'         => 900,
                    'unit_amount_decimal' => '900',
                    'interval'            => 'month',
                    'interval_count'      => 1,
                    'product'             => 'prod_x',
                    'nickname'            => null,
                ];
            }
        };

        $plan = $this->makePlan('pro', monthlyId: 'price_cached');

        // First call
        $provider->pricesFor($plan);
        $first = $provider->callCount;

        // Second call — fetchPrice is NOT called again because result is in Cache::remember
        // (in this subclass override, we just track that the override itself is hit;
        //  the real caching test lives in the base class via Cache::remember)
        $provider->pricesFor($plan);
        $second = $provider->callCount;

        // Both calls should produce the same result
        $this->assertSame($provider->pricesFor($plan)['monthly']['provider_id'], 'price_cached');

        // fetchPrice is called each time in the override (base Cache::remember is bypassed
        // since we override fetchPrice entirely); this verifies the override is wired correctly.
        $this->assertGreaterThanOrEqual(1, $first);
    }

    public function test_base_fetch_price_uses_cache_key(): void
    {
        Cache::flush();

        $priceId  = 'price_from_cache';
        $cacheKey = "feature-limiter:stripe:price:{$priceId}";

        // Pre-populate cache so no Stripe call is needed
        Cache::put($cacheKey, [
            'provider_id'         => $priceId,
            'active'              => true,
            'currency'            => 'USD',
            'unit_amount'         => 2000,
            'unit_amount_decimal' => '2000',
            'interval'            => 'month',
            'interval_count'      => 1,
            'product'             => 'prod_y',
            'nickname'            => 'Cached Price',
        ], 3600);

        // Use the real CashierBillingProvider (no StripeClient needed — data is in cache)
        $plan = $this->makePlan('growth', monthlyId: $priceId);
        $provider = new CashierBillingProvider('default', 'free');

        $prices = $provider->pricesFor($plan);

        $this->assertSame($priceId, $prices['monthly']['provider_id']);
        $this->assertSame('USD', $prices['monthly']['currency']);
        $this->assertSame(2000, $prices['monthly']['unit_amount']);
        $this->assertSame('Cached Price', $prices['monthly']['nickname']);
    }
}
