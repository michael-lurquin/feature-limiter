<?php

namespace MichaelLurquin\FeatureLimiter\Billing;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Contracts\BillingProvider;

class CashierBillingProvider implements BillingProvider
{
    public function __construct(protected string $subscriptionName = 'default', protected ?string $defaultPlanKey = 'free') {}

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

        $priceId = $sub->stripe_price ?? null;

        if ( !$priceId )
        {
            return $this->defaultPlanKey ? Plan::query()->where('key', $this->defaultPlanKey)->first() : null;
        }

        return Plan::query()
            ->where('stripe_price_monthly_id', $priceId)
            ->orWhere('stripe_price_yearly_id', $priceId)
            ->first()
                ?? ( $this->defaultPlanKey ? Plan::query()->where('key', $this->defaultPlanKey)->first() : null );
    }

    public function pricesFor(Plan $plan): array
    {
        return [
            'monthly' => $plan->stripe_price_monthly_id ? ['provider_id' => $plan->stripe_price_monthly_id] : null,
            'yearly'  => $plan->stripe_price_yearly_id ? ['provider_id' => $plan->stripe_price_yearly_id] : null,
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
}
