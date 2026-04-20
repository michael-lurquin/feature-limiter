<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Builders\Concerns\UsesBuilderAttributes;

class PlanBuilder
{
    use UsesBuilderAttributes;

    /**
     * Create a new plan builder.
     *
     * @param string $key Unique plan identifier (e.g. 'starter', 'pro', 'enterprise')
     * @param array $attributes Optional initial attributes for the plan
     */
    public function __construct(protected string $key, protected array $attributes = []) {}

    /**
     * Set the display name for the plan.
     *
     * If not specified, defaults to ucfirst($key) on save.
     *
     * @param string $name Display name (e.g. 'Starter Plan', 'Professional')
     * @return self
     */
    public function name(string $name): self
    {
        $this->attributes['name'] = $name;

        return $this;
    }

    /**
     * Set the description for the plan.
     *
     * Typically used for marketing/UI display.
     *
     * @param string $description Plan description (e.g. 'Great for small teams')
     * @return self
     */
    public function description(string $description): self
    {
        $this->attributes['description'] = $description;

        return $this;
    }

    /**
     * Set the sort order for the plan in pricing displays.
     *
     * Plans are sorted by this field (ascending). Lower values appear first.
     *
     * @param int $sort Sort position (e.g. 0, 1, 2)
     * @return self
     */
    public function sort(int $sort): self
    {
        $this->attributes['sort'] = $sort;

        return $this;
    }

    /**
     * Set whether the plan is active/available for new subscriptions.
     *
     * Inactive plans won't appear in pricing catalogs by default.
     *
     * @param bool $active Default: true. Pass false to deactivate the plan
     * @return self
     */
    public function active(bool $active = true): self
    {
        $this->attributes['active'] = $active;

        return $this;
    }

    /**
     * Set the monthly subscription price.
     *
     * Accepts decimal (9.99), string ("9.99"), or integer cents (999).
     * Stored internally in cents for precision.
     *
     * @param int|float|string|null $amount Monthly price (e.g. 9.99) or null to remove
     * @return self
     */
    public function monthly(int|float|string|null $amount = null): self
    {
        $this->attributes['price_monthly'] = $amount;

        return $this;
    }

    /**
     * Set the yearly subscription price.
     *
     * Accepts decimal (129), string ("129"), or integer cents (12900).
     * Stored internally in cents for precision.
     *
     * @param int|float|string|null $amount Yearly price (e.g. 129) or null to remove
     * @return self
     */
    public function yearly(int|float|string|null $amount = null): self
    {
        $this->attributes['price_yearly'] = $amount;

        return $this;
    }

    /**
     * Create or update the plan in the database.
     *
     * If a plan with this key already exists, it will be updated. Otherwise, it will be created.
     * Auto-generates name from key (ucfirst) if not explicitly set.
     *
     * @return Plan The created or updated Plan model instance
     *
     * @example
     * FeatureLimiter::plan('starter')
     *     ->name('Starter')
     *     ->monthly(9.99)
     *     ->yearly(99)
     *     ->save();
     */
    public function save(): Plan
    {
        $plan = Plan::query()->firstOrNew(['key' => $this->key]);

        if ( empty($this->attributes['name']) ) $this->attributes['name'] = ucfirst($this->key);

        $plan->fill($this->attributes);

        $plan->save();

        return $plan;
    }
}
