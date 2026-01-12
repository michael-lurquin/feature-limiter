<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Fakes;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Contracts\BillingProvider;

class AltBillingProvider implements BillingProvider
{
    public static $resolver = null;

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
        return [];
    }
}