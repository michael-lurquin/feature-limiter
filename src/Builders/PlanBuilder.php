<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Builders\Concerns\UsesBuilderAttributes;

class PlanBuilder
{
    use UsesBuilderAttributes;

    public function __construct(protected string $key, protected array $attributes = []) {}

    public function name(string $name): self
    {
        $this->attributes['name'] = $name;

        return $this;
    }

    public function description(string $description): self
    {
        $this->attributes['description'] = $description;

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

    public function monthly(int|float|string|null $amount = null): self
    {
        $this->attributes['price_monthly'] = $amount;

        return $this;
    }

    public function yearly(int|float|string|null $amount = null): self
    {
        $this->attributes['price_yearly'] = $amount;

        return $this;
    }

    public function save(): Plan
    {
        $plan = Plan::query()->firstOrNew(['key' => $this->key]);

        if ( empty($this->attributes['name']) ) $this->attributes['name'] = ucfirst($this->key);

        $plan->fill($this->attributes);

        $plan->save();

        return $plan;
    }
}
