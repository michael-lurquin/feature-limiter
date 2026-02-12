<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Concerns;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;
use MichaelLurquin\FeatureLimiter\Tests\Fakes\FakeBillingProvider;

trait InteractsWithFeatureLimiter
{
    protected function flBillable(int $id = 1): object
    {
        return new class($id) {
            public function __construct(public int $id) {}
        };
    }

    protected function flPlan(string $key, ?string $name = null, int $sort = 0, bool $active = true, array $attributes = []): Plan
    {
        return Plan::create([
            'key' => $key,
            'name' => $name ?? ucfirst(str_replace('_', ' ', $key)),
            'sort' => $sort,
            'active' => $active,
        ] + $attributes);
    }

    protected function flFeature(string $key, FeatureType $type, ?string $name = null, int $sort = 0, bool $active = true, ?string $group = null): Feature
    {
        return Feature::create([
            'key'  => $key,
            'name' => $name ?? ucfirst(str_replace('_', ' ', $key)),
            'type' => $type,
            'sort' => $sort,
            'active' => $active,
            'group' => $group,
        ]);
    }

    /**
     * Attach a feature to a plan with a raw value.
     * Examples:
     * - flGrantValue('starter', 'storage', '1GB')
     * - flGrantValue('starter', 'sites', 3)
     * - flGrantValue('pro', 'storage', 'unlimited')
     */
    protected function flGrantValue(string $planKey, string $featureKey, mixed $value): void
    {
        FeatureLimiter::grant($planKey)->feature($featureKey)->value($value);
    }

    /**
     * Attach a feature to a plan with a quota (INTEGER).
     */
    protected function flGrantQuota(string $planKey, string $featureKey, int $quota): void
    {
        FeatureLimiter::grant($planKey)->feature($featureKey)->quota($quota);
    }

    /**
     * Attach a feature to a plan as enabled/disabled (BOOLEAN).
     */
    protected function flGrantEnabled(string $planKey, string $featureKey, bool $enabled = true): void
    {
        $b = FeatureLimiter::grant($planKey)->feature($featureKey);

        $enabled ? $b->enabled() : $b->disabled();
    }

    /**
     * Make FeatureLimiter::for($billable) resolve the given plan key.
     */
    protected function flResolvePlan(string $planKey): void
    {
        FakeBillingProvider::$resolver = fn () => Plan::where('key', $planKey)->first();
    }

    /**
     * Shortcut: returns FeatureLimiter::for($billable)
     */
    protected function flReader(object $billable)
    {
        return FeatureLimiter::for($billable);
    }

    /**
     * Build a starter plan + features, attach quotas/values, resolve the plan for a fresh billable,
     * and return the reader.
     *
     * Example:
     *  $reader = $this->starterReader([
     *      'sites' => ['type' => FeatureType::INTEGER, 'quota' => 10],
     *      'storage' => ['type' => FeatureType::STORAGE, 'value' => '1GB'],
     *  ]);
     */
    private function starterReader(array $features): mixed
    {
        $this->flPlan('starter');

        foreach ($features as $key => $def)
        {
            $type = $def['type'] ?? null;

            if ( !$type instanceof FeatureType )
            {
                throw new \InvalidArgumentException("Invalid feature definition for {$key}");
            }

            $this->flFeature($key, $type);

            if ( $type === FeatureType::INTEGER && array_key_exists('quota', $def) )
            {
                $this->flGrantQuota('starter', $key, (int) $def['quota']);
            }
            elseif ( $type === FeatureType::BOOLEAN && array_key_exists('enabled', $def) )
            {
                $this->flGrantEnabled('starter', $key, (bool) $def['enabled']);
            }
            elseif ( array_key_exists('value', $def) )
            {
                // STORAGE and any other raw values go through value()
                $this->flGrantValue('starter', $key, $def['value']);
            }
        }

        $billable = $this->flBillable(1);

        $this->flResolvePlan('starter');

        return $this->flReader($billable);
    }

    protected function flSetupStarterWithSitesAndStorage(): object
    {
        $this->flPlan('starter');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flFeature('storage', FeatureType::STORAGE);

        $this->flGrantQuota('starter', 'sites', 10);
        $this->flGrantValue('starter', 'storage', '1GB');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');

        return $billable;
    }
}
