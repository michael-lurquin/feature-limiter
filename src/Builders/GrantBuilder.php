<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use MichaelLurquin\FeatureLimiter\Models\Plan;

class GrantBuilder
{
    public function __construct(protected string $planKey) {}

    public function feature(string $featureKey): GrantFeatureBuilder
    {
        return new GrantFeatureBuilder($this->planKey, $featureKey);
    }

    public function features(array $map): Plan
    {
        return (new GrantFeaturesBuilder($this->planKey, $map))->save();
    }
}
