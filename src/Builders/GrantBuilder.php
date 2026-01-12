<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use MichaelLurquin\FeatureLimiter\Models\Plan;

class GrantBuilder
{
    public function __construct(protected string|Plan $plan) {}

    protected function resolvePlanKey(): string
    {
        if ($this->plan instanceof Plan) {
            return (string) $this->plan->key;
        }

        return $this->plan;
    }

    public function feature(string $featureKey): GrantFeatureBuilder
    {
        return new GrantFeatureBuilder($this->resolvePlanKey(), $featureKey);
    }

    public function features(array $map): Plan
    {
        return (new GrantFeaturesBuilder($this->resolvePlanKey(), $map))->save();
    }
}
