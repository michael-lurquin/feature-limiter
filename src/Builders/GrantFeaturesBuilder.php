<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Builders\Concerns\ResolvesPlanAndFeatures;
use MichaelLurquin\FeatureLimiter\Builders\Concerns\UsesFeatureValueParser;

class GrantFeaturesBuilder
{
    use ResolvesPlanAndFeatures;
    use UsesFeatureValueParser;

    public function __construct(protected string $planKey, protected array $featuresMap) {}

    public function save(): Plan
    {
        $plan = $this->requirePlan($this->planKey);

        $keys = array_keys($this->featuresMap);

        $features = $this->requireFeatures($keys);

        $sync = [];

        foreach ($this->featuresMap as $featureKey => $rawValue)
        {
            $feature = $features[$featureKey];

            [$value, $isUnlimited] = $this->featureValueParser()->parse($feature, $rawValue);

            $sync[$feature->id] = [
                'value' => $value,
                'is_unlimited' => $isUnlimited,
            ];
        }

        $plan->features()->syncWithoutDetaching($sync);

        return $plan->refresh();
    }
}
