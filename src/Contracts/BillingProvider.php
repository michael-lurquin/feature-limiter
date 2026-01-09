<?php

namespace MichaelLurquin\FeatureLimiter\Contracts;

use MichaelLurquin\FeatureLimiter\Models\Plan;

interface BillingProvider
{
    public function resolvePlanFor(mixed $billable): ?Plan;

    public function pricesFor(Plan $plan): array;
}
