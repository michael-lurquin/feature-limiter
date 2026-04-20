<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use MichaelLurquin\FeatureLimiter\Models\Plan;

class GrantBuilder
{
    /**
     * Create a new grant builder for assigning features to a plan.
     *
     * @param string|Plan $plan Plan key (e.g. 'starter') or Plan model instance
     */
    public function __construct(protected string|Plan $plan) {}

    /**
     * Resolve the plan key from either a string key or Plan model instance.
     *
     * @return string The plan's key identifier
     */
    protected function resolvePlanKey(): string
    {
        if ($this->plan instanceof Plan) {
            return (string) $this->plan->key;
        }

        return $this->plan;
    }

    /**
     * Create a builder to assign a single feature/quota to this plan.
     *
     * @param string $featureKey Feature identifier (e.g. 'sites', 'storage', 'custom_code')
     * @return GrantFeatureBuilder Feature grant builder for configuring quota/value
     *
     * @example
     * FeatureLimiter::grant('starter')->feature('sites')->quota(3)->save();
     */
    public function feature(string $featureKey): GrantFeatureBuilder
    {
        return new GrantFeatureBuilder($this->resolvePlanKey(), $featureKey);
    }

    /**
     * Assign multiple features with their quotas/values to this plan in one operation.
     *
     * @param array<string, int|string|bool|null> $map Feature key => quota/value mapping
     *        - For INTEGER features: integer values (e.g. 'sites' => 3)
     *        - For STORAGE features: string format (e.g. 'storage' => '1GB')
     *        - For BOOLEAN features: boolean or 1/0 (e.g. 'custom_code' => true)
     *        - For unlimited: use 'unlimited', -1, null, or set unlimited=true in value()
     * @return Plan The updated Plan model instance
     *
     * @example
     * FeatureLimiter::grant('starter')->features([
     *     'sites' => 3,
     *     'storage' => '1GB',
     *     'custom_code' => true,
     * ])->save();
     */
    public function features(array $map): Plan
    {
        return (new GrantFeaturesBuilder($this->resolvePlanKey(), $map))->save();
    }
}
