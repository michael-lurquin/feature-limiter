<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use MichaelLurquin\FeatureLimiter\Models\Plan;

class PlansBuilder
{
    public function __construct(protected array $plans) {}

    public function save(): array
    {
        $newPlans = [];

        $index = 0;

        foreach ($this->plans as $key => $attributes)
        {
            // Support list syntax: ['free', 'starter', ...]
            if ( is_int($key) && is_string($attributes) )
            {
                $key = $attributes;

                $attributes = [];
            }

            // Optional: support array items with an explicit key
            if ( is_int($key) && is_array($attributes) && isset($attributes['key']) )
            {
                $key = (string) $attributes['key'];

                unset($attributes['key']);
            }

            $plan = Plan::query()->firstOrNew(['key' => $key]);

            if ( empty($attributes['name']) )
            {
                $attributes['name'] = ucfirst($key);
            }

            if ( !array_key_exists('sort', $attributes) )
            {
                $attributes['sort'] = $index;
            }

            $plan->fill($attributes);

            $plan->save();

            $newPlans[$key] = $plan;

            $index++;
        }

        return $newPlans;
    }
}
