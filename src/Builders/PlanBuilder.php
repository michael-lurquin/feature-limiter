<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use MichaelLurquin\FeatureLimiter\Models\Plan;

class PlanBuilder
{
    public function __construct(
        protected string $key,
        protected array $attributes = []
    ) {}

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

    public function firstOrCreate(): Plan
    {
        return Plan::firstOrCreate(['key' => $this->key], $this->attributes);
    }

    public function upsert(): Plan
    {
        $plan = Plan::firstOrNew(['key' => $this->key]);
        $plan->fill($this->attributes);
        $plan->save();

        return $plan;
    }

    public function save(): Plan
    {
        return $this->upsert();
    }
}
