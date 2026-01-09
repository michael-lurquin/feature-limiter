<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Support\FeatureValueParser;

class GrantFeaturesBuilder
{
    public function __construct(protected string $planKey, protected array $featuresMap) {}

    public function save(): Plan
    {
        $plan = Plan::query()->where('key', $this->planKey)->first();

        if ( !$plan )
        {
            throw new InvalidArgumentException("Plan not found: {$this->planKey}");
        }

        $keys = array_keys($this->featuresMap);

        $features = Feature::query()
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        $missing = array_values(array_diff($keys, $features->keys()->all()));

        if ( !empty($missing) )
        {
            throw new InvalidArgumentException('Features not found: ' . implode(', ', $missing));
        }

        $parser = new FeatureValueParser();

        $sync = [];

        foreach ($this->featuresMap as $featureKey => $rawValue)
        {
            $feature = $features[$featureKey];

            [$value, $isUnlimited] = $parser->parse($feature, $rawValue);

            $sync[$feature->id] = [
                'value' => $value,
                'is_unlimited' => $isUnlimited,
            ];
        }

        $plan->features()->syncWithoutDetaching($sync);

        return $plan->refresh();
    }
}
