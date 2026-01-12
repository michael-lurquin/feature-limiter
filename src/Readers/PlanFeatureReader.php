<?php

namespace MichaelLurquin\FeatureLimiter\Readers;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;

class PlanFeatureReader
{
    private array $rawCache = [];

    public function __construct(protected Plan $plan) {}

    public function raw(string $featureKey): ?array
    {
        if ( array_key_exists($featureKey, $this->rawCache) )
        {
            return $this->rawCache[$featureKey];
        }

        $feature = $this->plan->features()->where('key', $featureKey)->first();

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

    public function unlimited(string $featureKey): bool
    {
        $raw = $this->raw($featureKey);

        return $raw ? (bool) $raw['is_unlimited'] : false;
    }

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
