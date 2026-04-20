<?php

namespace MichaelLurquin\FeatureLimiter;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Builders\PlanBuilder;
use MichaelLurquin\FeatureLimiter\Builders\GrantBuilder;
use MichaelLurquin\FeatureLimiter\Builders\PlansBuilder;
use MichaelLurquin\FeatureLimiter\Billing\BillingManager;
use MichaelLurquin\FeatureLimiter\Builders\FeatureBuilder;
use MichaelLurquin\FeatureLimiter\Builders\FeaturesBuilder;
use MichaelLurquin\FeatureLimiter\Readers\PlanCatalogReader;
use MichaelLurquin\FeatureLimiter\Readers\PlanFeatureReader;
use MichaelLurquin\FeatureLimiter\Readers\BillableFeatureReader;
use MichaelLurquin\FeatureLimiter\Repositories\FeatureUsageRepository;

class FeatureLimiterManager
{
    public function __construct(protected BillingManager $billing, protected FeatureUsageRepository $usages) {}

    /**
     * Create a new plan builder for defining a subscription plan.
     *
     * @param string $key Unique identifier for the plan (e.g. 'starter', 'pro')
     * @return PlanBuilder Plan builder instance for fluent configuration
     *
     * @example
     * FeatureLimiter::plan('starter')->name('Starter')->monthly(9.99)->save();
     */
    public function plan(string $key): PlanBuilder
    {
        return new PlanBuilder($key);
    }

    /**
     * Create multiple plans in a single operation.
     *
     * @param array $plans Plan definitions: ['plan_key' => ['name' => '...', 'sort' => 0, ...], ...]
     * @return PlansBuilder Plans builder instance for batch configuration
     *
     * @example
     * FeatureLimiter::plans(['free', 'starter', 'pro'])->save();
     */
    public function plans(array $plans): PlansBuilder
    {
        return new PlansBuilder($plans);
    }

    /**
     * Create a new feature builder for defining a feature/capability.
     *
     * @param string $key Unique identifier for the feature (e.g. 'sites', 'storage', 'custom_code')
     * @return FeatureBuilder Feature builder instance for fluent configuration
     *
     * @example
     * FeatureLimiter::feature('sites')->type(FeatureType::INTEGER)->save();
     */
    public function feature(string $key): FeatureBuilder
    {
        return new FeatureBuilder($key);
    }

    /**
     * Create multiple features in a single operation.
     *
     * @param array $features Feature definitions: ['feature_key' => ['type' => FeatureType::INTEGER, ...], ...]
     * @return FeaturesBuilder Features builder instance for batch configuration
     *
     * @example
     * FeatureLimiter::features(['sites' => ['type' => FeatureType::INTEGER]])->save();
     */
    public function features(array $features): FeaturesBuilder
    {
        return new FeaturesBuilder($features);
    }

    /**
     * Create a grant builder to assign features/quotas to a plan.
     *
     * @param string|Plan $plan Plan key (e.g. 'starter') or Plan model instance
     * @return GrantBuilder Grant builder instance for assigning features to the plan
     *
     * @example
     * FeatureLimiter::grant('starter')->feature('sites')->quota(3)->save();
     */
    public function grant(string|Plan $plan): GrantBuilder
    {
        return new GrantBuilder($plan);
    }

    /**
     * Get a plan reader to view quotas and settings for a specific plan.
     *
     * @param string $key Plan identifier (e.g. 'starter', 'pro')
     * @return PlanFeatureReader Plan feature reader for querying quota information
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If plan does not exist
     *
     * @example
     * FeatureLimiter::viewPlan('starter')->quota('sites'); // Returns: 3
     */
    public function viewPlan(string $key): PlanFeatureReader
    {
        $plan = Plan::where('key', $key)->firstOrFail();

        return new PlanFeatureReader($plan);
    }

    /**
     * Get a billable feature reader to track usage and consumption for an entity (user, tenant, team, etc.).
     *
     * @param mixed $billable Any object or Eloquent model with an `id` property
     * @return BillableFeatureReader Billable feature reader for consumption and quota tracking
     *
     * @example
     * FeatureLimiter::for($user)->consume('sites', 1);
     * FeatureLimiter::for($user)->quota('sites'); // Returns: 3
     */
    public function for(mixed $billable): BillableFeatureReader
    {
        return new BillableFeatureReader($billable, $this->billing, $this->usages);
    }

    /**
     * Get a catalog reader to generate pricing cards and comparison tables for UI rendering.
     *
     * @return PlanCatalogReader Catalog reader for building pricing page data structures
     *
     * @example
     * $cards = FeatureLimiter::catalog()->plansCards(['sites', 'storage']);
     * $table = FeatureLimiter::catalog()->comparisonTable();
     */
    public function catalog(): PlanCatalogReader
    {
        return new PlanCatalogReader($this->billing);
    }
}

