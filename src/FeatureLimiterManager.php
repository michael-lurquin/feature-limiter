<?php

namespace MichaelLurquin\FeatureLimiter;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Builders\PlanBuilder;
use MichaelLurquin\FeatureLimiter\Builders\GrantBuilder;
use MichaelLurquin\FeatureLimiter\Builders\FeatureBuilder;

class FeatureLimiterManager
{
    public function plan(string $key): PlanBuilder
    {
        return new PlanBuilder($key);
    }

    public function feature(string $key): FeatureBuilder
    {
        return new FeatureBuilder($key);
    }

    public function grant(string|Plan $plan): GrantBuilder
    {
        return new GrantBuilder($plan);
    }
}
