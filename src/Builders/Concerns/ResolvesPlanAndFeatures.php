<?php

namespace MichaelLurquin\FeatureLimiter\Builders\Concerns;

use InvalidArgumentException;
use Illuminate\Support\Collection;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;

trait ResolvesPlanAndFeatures
{
    protected function requirePlan(string $planKey): Plan
    {
        $plan = Plan::query()->where('key', $planKey)->first();

        if ( !$plan )
        {
            throw new InvalidArgumentException("Plan not found: {$planKey}");
        }

        return $plan;
    }

    protected function requireFeature(string $featureKey): Feature
    {
        $feature = Feature::query()->where('key', $featureKey)->first();

        if ( !$feature )
        {
            throw new InvalidArgumentException("Feature not found: {$featureKey}");
        }

        return $feature;
    }

    /**
     * @return Collection<string, Feature>
     */
    protected function requireFeatures(array $keys): Collection
    {
        $features = Feature::query()
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        $missing = array_values(array_diff($keys, $features->keys()->all()));

        if ( !empty($missing) )
        {
            throw new InvalidArgumentException('Features not found: ' . implode(', ', $missing));
        }

        return $features;
    }
}
