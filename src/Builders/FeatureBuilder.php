<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Builders\Concerns\UsesBuilderAttributes;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Enums\ResetPeriod;

class FeatureBuilder
{
    use UsesBuilderAttributes;

    public function __construct(protected string $key, protected array $attributes = []) {}

    public function name(string $name): self
    {
        $this->attributes['name'] = $name;

        return $this;
    }

    public function description(?string $description): self
    {
        $this->attributes['description'] = $description;

        return $this;
    }

    public function group(?string $group): self
    {
        $this->attributes['group'] = $group;

        return $this;
    }

    /**
     * Accepts FeatureType enum OR a string like 'integer', 'boolean', 'storage'.
     */
    public function type(FeatureType|string $type): self
    {
        $typeEnum = $type instanceof FeatureType ? $type : FeatureType::tryFrom($type);

        if ( !$typeEnum )
        {
            throw new InvalidArgumentException("Invalid feature type: {$type}");
        }

        $this->attributes['type'] = $typeEnum;

        return $this;
    }

    public function unit(?string $unit): self
    {
        $this->attributes['unit'] = $unit;

        return $this;
    }

    /**
     * Accepts ResetPeriod enum OR a string like 'none', 'daily', 'monthly', 'yearly'.
     */
    public function reset(ResetPeriod|string $period): self
    {
        $periodEnum = $period instanceof ResetPeriod ? $period : ResetPeriod::tryFrom($period);

        if ( !$periodEnum )
        {
            throw new InvalidArgumentException("Invalid reset period: {$period}");
        }

        $this->attributes['reset_period'] = $periodEnum->value;

        return $this;
    }

    public function sort(int $sort): self
    {
        $this->attributes['sort'] = $sort;

        return $this;
    }

    public function active(bool $active = true): self
    {
        $this->attributes['active'] = $active;

        return $this;
    }

    public function save(): Feature
    {
        $feature = Feature::query()->firstOrNew(['key' => $this->key]);

        $this->fillAttributes($feature);

        $feature->save();

        return $feature;
    }
}
