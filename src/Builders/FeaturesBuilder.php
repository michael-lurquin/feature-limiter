<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Feature;

class FeaturesBuilder
{
    public function __construct(protected array $features) {}

    public function save(): array
    {
        $newFeatures = [];

        $index = 0;

        foreach ($this->features as $key => $attributes)
        {
            // Enforce attributes array
            if ( !is_array($attributes) )
            {
                throw new InvalidArgumentException("Feature '{$key}' must define an attributes array.");
            }

            $feature = Feature::query()->firstOrNew(['key' => $key]);

            if ( empty($attributes['name']) )
            {
                $attributes['name'] = ucfirst(str_replace('_', ' ', $key));
            }

            if ( !array_key_exists('sort', $attributes) )
            {
                $attributes['sort'] = $index;
            }

            $feature->fill($attributes);

            $feature->save();

            $newFeatures[$key] = $feature;

            $index++;
        }

        return $newFeatures;
    }
}
