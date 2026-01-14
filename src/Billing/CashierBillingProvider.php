<?php

namespace MichaelLurquin\FeatureLimiter\Billing;

use RuntimeException;
use Stripe\StripeClient;
use Illuminate\Support\Facades\Cache;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Contracts\BillingProvider;

class CashierBillingProvider implements BillingProvider
{
    public function __construct(
        protected string $subscriptionName = 'default', 
        protected ?string $defaultPlanKey = 'free',
        protected ?StripeClient $stripe = null,
        protected int $cacheSeconds = 3600 // 1h
    ) {}

    public function resolvePlanFor(mixed $billable): ?Plan
    {
        if ( !is_object($billable) || ! method_exists($billable, 'subscription') )
        {
            return $this->defaultPlanKey ? Plan::query()->where('key', $this->defaultPlanKey)->first() : null;
        }

        $sub = $billable->subscription($this->subscriptionName);

        if ( !$sub || !$sub->valid() )
        {
            return $this->defaultPlanKey ? Plan::query()->where('key', $this->defaultPlanKey)->first() : null;
        }

        $priceId = $this->extractPriceId($sub);

        if ( !$priceId )
        {
            return $this->defaultPlanKey ? Plan::query()->where('key', $this->defaultPlanKey)->first() : null;
        }

        return Plan::query()
            ->where('provider_monthly_id', $priceId)
            ->orWhere('provider_yearly_id', $priceId)
            ->first()
                ?? ( $this->defaultPlanKey ? Plan::query()->where('key', $this->defaultPlanKey)->first() : null );
    }

    public function pricesFor(Plan $plan): array
    {
        return [
            'monthly' => $plan->provider_monthly_id ? $this->fetchPrice($plan->provider_monthly_id) : null,
            'yearly'  => $plan->provider_yearly_id ? $this->fetchPrice($plan->provider_yearly_id) : null,
        ];
    }

    protected function extractPriceId($subscription): ?string
    {
        // Cashier: souvent $subscription->stripe_price (selon versions)
        if ( property_exists($subscription, 'stripe_price') && $subscription->stripe_price )
        {
            return $subscription->stripe_price;
        }

        // Autre option: via items
        if ( method_exists($subscription, 'items') && $subscription->items && $subscription->items->count() )
        {
            $item = $subscription->items->first();

            // Selon versions: stripe_price sur item, ou price sur l'objet stripe
            if ( property_exists($item, 'stripe_price') && $item->stripe_price )
            {
                return $item->stripe_price;
            }
        }

        return null;
    }

    protected function stripe(): StripeClient
    {
        if ( $this->stripe ) return $this->stripe;

        $key = config('cashier.secret') ?: env('STRIPE_SECRET');

        if ( !$key ) throw new RuntimeException("Stripe secret key not configured (cashier.secret / STRIPE_SECRET).");

        return $this->stripe = new StripeClient($key);
    }

    protected function fetchPrice(string $priceId): array
    {
        $cacheKey = "feature-limiter:stripe:price:{$priceId}";

        return Cache::remember($cacheKey, $this->cacheSeconds, function () use($priceId) {
            $price = $this->stripe()->prices->retrieve($priceId, []);

            $recurring = $price->recurring ?? null;

            return [
                'provider_id' => $price->id,
                'active' => (bool) $price->active,
                'currency' => strtoupper((string) $price->currency),
                'unit_amount' => $price->unit_amount, // ex 1200
                'unit_amount_decimal' => $price->unit_amount_decimal ?? null,
                'interval' => $recurring?->interval, // 'month'|'year'|null
                'interval_count' => $recurring?->interval_count, // 1, 12, ...
                'product' => $price->product,
                'nickname' => $price->nickname,
            ];
        });
    }
}
