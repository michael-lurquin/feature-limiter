<?php

namespace MichaelLurquin\FeatureLimiter\Readers;

use Illuminate\Support\Collection;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Billing\BillingManager;

class PlanCatalogReader
{
    public function __construct(protected BillingManager $billing, protected ?string $provider = null) {}

    public function includePrices(): self
    {
        $this->provider = config('feature-limiter.billing.default', 'cashier');

        return $this;
    }

    /**
     * Cards view (like pricing cards):
     * - lists plans
     * - attaches a small set of "featured" features
     * - optionally includes prices from billing provider
     */
    public function plansCards(array $featured = [], bool $onlyActivePlans = true, bool $onlyActiveFeatures = true): array
    {
        $plans = $this->plansQuery($onlyActivePlans, $onlyActiveFeatures)->get();

        $providerInstance = $this->provider !== null ? $this->billing->provider($this->provider) : null;

        return $plans->map(function (Plan $plan) use ($featured, $providerInstance)
        {
            $planReader = new PlanFeatureReader($plan);

            $featuredRows = [];

            foreach ($featured as $featureKey)
            {
                $value = $planReader->value($featureKey);

                // If feature not found in plan, just skip it.
                if ( $value === null ) continue;

                $feature = $plan->features->firstWhere('key', $featureKey);

                $featuredRows[] = [
                    'key' => $featureKey,
                    'label' => $feature?->name ?? $featureKey,
                    'unit' => $feature?->unit,
                    'group' => $feature?->group,
                    'value' => $value, // bool|int|string|"unlimited"
                ];
            }

            return [
                'key' => $plan->key,
                'name' => $plan->name,
                'sort' => (int) $plan->sort,
                'active' => (bool) $plan->active,
                'prices' => $providerInstance ? $providerInstance->pricesFor($plan) : [
                    'monthly' => $plan->price_monthly,
                    'yearly' => $plan->price_yearly,
                ],
                'featured' => $featuredRows,
            ];
        })->values()->all();
    }

    /**
     * Comparison table grouped by feature.group
     */
    public function comparisonTable(bool $onlyActivePlans = true, bool $onlyActiveFeatures = true): array
    {
        $plans = $this->plansQuery($onlyActivePlans, $onlyActiveFeatures)->get();

        $planHeaders = $plans->map(fn (Plan $p) => [
            'key' => $p->key,
            'name' => $p->name,
            'sort' => (int) $p->sort,
            'active' => (bool) $p->active,
        ])->values()->all();

        // Build a map of all features across all plans (by key) to create consistent rows.
        // Because plans->features is eager loaded, this is in-memory.
        $allFeatures = $plans
            ->flatMap(fn (Plan $p) => $p->features)
            ->keyBy('key')
            ->sortBy(fn ($f) => [$f->group ?? '', (int) $f->sort, $f->name])
            ->values();

        // Group features by group key (null => "other")
        $groups = $allFeatures
            ->groupBy(fn ($f) => $f->group ?: 'other')
            ->map(function (Collection $features, string $groupKey) use ($plans) {
                $rows = $features->map(function ($feature) use ($plans) {
                    $values = [];

                    foreach ($plans as $plan)
                    {
                        $planReader = new PlanFeatureReader($plan);
                        $values[$plan->key] = $planReader->value($feature->key);
                    }

                    return [
                        'key' => $feature->key,
                        'label' => $feature->name,
                        'unit' => $feature->unit,
                        'sort' => (int) $feature->sort,
                        'values' => $values, // planKey => bool|int|string|"unlimited"|null
                    ];
                })->values()->all();

                return [
                    'key' => $groupKey,
                    'features' => $rows,
                ];
            })->values()->all();

        $providerInstance = $this->provider !== null ? $this->billing->provider($this->provider) : null;
        $prices = [];

        foreach ($plans as $plan)
        {
            $prices[$plan->key] = $providerInstance ? $providerInstance->pricesFor($plan) : [
                'monthly' => $plan->price_monthly,
                'yearly' => $plan->price_yearly,
            ];
        }

        return [
            'plans' => $planHeaders,
            'prices' => $prices, // planKey => provider prices array (optional)
            'groups' => $groups,
        ];
    }

    protected function plansQuery(bool $onlyActivePlans, bool $onlyActiveFeatures)
    {
        return Plan::query()
            ->when($onlyActivePlans, fn ($q) => $q->where('active', true))
            ->orderBy('sort')
            ->orderBy('id')
            ->with(['features' => function ($q) use ($onlyActiveFeatures) {
                $q->when($onlyActiveFeatures, fn ($qq) => $qq->where('active', true))
                  ->orderBy('group')
                  ->orderBy('sort')
                  ->orderBy('id');
            }]);
    }
}
