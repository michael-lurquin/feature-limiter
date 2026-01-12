<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use MichaelLurquin\FeatureLimiter\Builders\Concerns\UsesBuilderAttributes;
use MichaelLurquin\FeatureLimiter\Models\Plan;

class PlanBuilder
{
    use UsesBuilderAttributes;

    public function __construct(protected string $key, protected array $attributes = []) {}

    public function name(string $name): self
    {
        $this->attributes['name'] = $name;

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

    public function save(): Plan
    {
        $plan = Plan::query()->firstOrNew(['key' => $this->key]);

        $this->fillAttributes($plan);

        $plan->save();

        return $plan;
    }
}
