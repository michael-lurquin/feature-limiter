<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use MichaelLurquin\FeatureLimiter\Models\Plan;

class PlanBuilder
{
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

        if ( !empty($this->attributes) )
        {
            $plan->fill($this->attributes);
        }

        $plan->save();

        return $plan;
    }
}
