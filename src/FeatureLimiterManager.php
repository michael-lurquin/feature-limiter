<?php

namespace MichaelLurquin\FeatureLimiter;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Builders\PlanBuilder;
use MichaelLurquin\FeatureLimiter\Builders\GrantBuilder;
use MichaelLurquin\FeatureLimiter\Billing\BillingManager;
use MichaelLurquin\FeatureLimiter\Builders\FeatureBuilder;
use MichaelLurquin\FeatureLimiter\Readers\PlanFeatureReader;
use MichaelLurquin\FeatureLimiter\Readers\BillableFeatureReader;
use MichaelLurquin\FeatureLimiter\Repositories\FeatureUsageRepository;

class FeatureLimiterManager
{
    public function __construct(protected BillingManager $billing, protected FeatureUsageRepository $usages) {}

    // Builders
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

    // Readers
    public function viewPlan(string $key): PlanFeatureReader
    {
        $plan = Plan::where('key', $key)->firstOrFail();

        return new PlanFeatureReader($plan);
    }

    public function for(mixed $billable): BillableFeatureReader
    {
        return new BillableFeatureReader($billable, $this->billing, $this->usages);
    }
}
