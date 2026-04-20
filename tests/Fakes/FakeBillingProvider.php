<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Fakes;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Contracts\BillingProvider;

class FakeBillingProvider implements BillingProvider
{
    public static $resolver = null;
    public static $pricesResolver = null;

    public function resolvePlanFor(mixed $billable): ?Plan
    {
        if ( is_callable(static::$resolver) )
        {
            return (static::$resolver)($billable);
        }

        return null;
    }

    public function pricesFor(Plan $plan): array
    {
        if ( is_callable(static::$pricesResolver) )
        {
            return (static::$pricesResolver)($plan);
        }

        return [];
    }
}
