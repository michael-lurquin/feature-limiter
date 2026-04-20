<?php

namespace MichaelLurquin\FeatureLimiter\Readers;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;

class PlanFeatureReader
{
    private array $rawCache = [];

    /**
     * Create a plan feature reader for viewing plan quotas and settings.
     *
     * @param Plan $plan The plan model to read features from
     */
    public function __construct(protected Plan $plan) {}

    /**
     * Retrieve plan pricing from the configured billing provider.
     *
     * Returns pricing information for the plan (e.g. monthly/yearly prices from Stripe via Cashier).
     * By default, returns an empty array unless a billing provider is configured and has prices set.
     *
     * @return array<string, mixed> Pricing array structure depends on the billing provider implementation
     *
     * @example
     * $prices = FeatureLimiter::viewPlan('starter')->prices();
     * // For Cashier: ['monthly' => [...], 'yearly' => [...]]
     */
    public function prices(): array
    {
        return $this->plan->prices();
    }

    /**
     * Get raw feature data (internal cache-optimized method).
     *
     * @param string $featureKey Feature identifier (e.g. 'sites', 'storage')
     * @return array<string, mixed>|null Feature metadata: ['type' => FeatureType, 'value' => string|null, 'is_unlimited' => bool]
     */
    public function raw(string $featureKey): ?array
    {
        if ( array_key_exists($featureKey, $this->rawCache) )
        {
            return $this->rawCache[$featureKey];
        }

        if ( $this->plan->relationLoaded('features') )
        {
            $feature = $this->plan->features->firstWhere('key', $featureKey);
        }
        else
        {
            $feature = $this->plan->features()->where('key', $featureKey)->first();
        }

        if ( !$feature )
        {
            $this->rawCache[$featureKey] = null;

            return null;
        }

        $raw = [
            'type' => $feature->type, // enum FeatureType
            'value' => $feature->planFeature->value, // string|null
            'is_unlimited' => (bool) $feature->planFeature->is_unlimited,
        ];

        $this->rawCache[$featureKey] = $raw;

        return $raw;
    }

    /**
     * Get the quota limit assigned to a feature in this plan.
     *
     * For BOOLEAN features, returns null (use `enabled()` instead).
     * For unlimited features, returns the string `'unlimited'`.
     * For INTEGER features, returns the numeric limit.
     * For STORAGE features, returns the string format (e.g. '1GB', '500MB').
     *
     * @param string $featureKey Feature identifier (e.g. 'sites', 'storage')
     * @return int|string|null The quota limit, 'unlimited', or null if feature not assigned to plan
     *
     * @example
     * FeatureLimiter::viewPlan('starter')->quota('sites');  // Returns: 3
     * FeatureLimiter::viewPlan('pro')->quota('storage');    // Returns: '1GB' or 'unlimited'
     * FeatureLimiter::viewPlan('starter')->quota('unknown'); // Returns: null
     */
    public function quota(string $featureKey): int|string|null
    {
        $raw = $this->raw($featureKey);

        if ( !$raw )
        {
            return null;
        }

        if ( $raw['type'] === FeatureType::BOOLEAN )
        {
            return null;
        }

        if ( $raw['is_unlimited'] )
        {
            return 'unlimited';
        }

        if ( $raw['value'] === null )
        {
            return null;
        }

        return match ($raw['type']) {
            FeatureType::INTEGER => (int) $raw['value'],
            FeatureType::STORAGE => (string) $raw['value'],
            default => null,
        };
    }

    /**
     * Check if a BOOLEAN feature is enabled in this plan.
     *
     * @param string $featureKey Feature identifier (e.g. 'custom_code', 'api_access')
     * @return bool True if the BOOLEAN feature is enabled in this plan, false otherwise
     *
     * @example
     * FeatureLimiter::viewPlan('starter')->enabled('custom_code'); // Returns: true|false
     */
    public function enabled(string $featureKey): bool
    {
        $raw = $this->raw($featureKey);

        if ( !$raw ) return false;

        if ( $raw['type'] !== FeatureType::BOOLEAN )
        {
            return false;
        }

        return $raw['value'] === '1';
    }

    /**
     * Check if a feature is marked as unlimited in this plan.
     *
     * @param string $featureKey Feature identifier (e.g. 'storage', 'api_calls')
     * @return bool True if the feature has unlimited quota, false otherwise
     *
     * @example
     * FeatureLimiter::viewPlan('pro')->unlimited('storage'); // Returns: true
     */
    public function unlimited(string $featureKey): bool
    {
        $raw = $this->raw($featureKey);

        return $raw ? (bool) $raw['is_unlimited'] : false;
    }

    /**
     * Get the assigned value of a feature, formatted according to its type.
     *
     * Returns:
     * - BOOLEAN: true/false
     * - INTEGER: numeric value (e.g. 3)
     * - STORAGE: string format (e.g. '1GB') or 'unlimited'
     * - Not assigned: null
     *
     * @param string $featureKey Feature identifier (e.g. 'sites', 'storage', 'custom_code')
     * @return mixed The feature value, formatted per its type, or null if not assigned
     *
     * @example
     * FeatureLimiter::viewPlan('starter')->value('sites');       // Returns: 3
     * FeatureLimiter::viewPlan('starter')->value('custom_code'); // Returns: true
     * FeatureLimiter::viewPlan('pro')->value('storage');         // Returns: 'unlimited'
     */
    public function value(string $featureKey): mixed
    {
        $raw = $this->raw($featureKey);

        if ( !$raw )
        {
            return null;
        }

        if ( $raw['is_unlimited'] )
        {
            return 'unlimited';
        }

        return match ($raw['type']) {
            FeatureType::BOOLEAN => $raw['value'] === '1',
            FeatureType::INTEGER => (int) $raw['value'],
            FeatureType::STORAGE => (string) $raw['value'],
        };
    }
}
