<?php

namespace MichaelLurquin\FeatureLimiter\Repositories;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Models\FeatureUsage;
use MichaelLurquin\FeatureLimiter\Support\PeriodResolver;

class FeatureUsageRepository
{
    private array $featureCache = [];

    public function __construct(protected PeriodResolver $periods) {}

    public function used(mixed $billable, string $featureKey): int
    {
        $feature = $this->findFeature($featureKey);

        if ( !$feature )
        {
            throw new InvalidArgumentException("Feature not found: {$featureKey}");
        }

        [$type, $id, $start, $end] = $this->usageContext($billable, $feature);

        $usage = $this->usageQuery($type, $id, $feature, $start)->first();

        return (int) ( $usage?->used ?? 0 );
    }

    public function set(mixed $billable, string $featureKey, int $value): int
    {
        if ( $value < 0 )
        {
            throw new InvalidArgumentException("Usage value must be >= 0.");
        }

        $feature = $this->findFeatureOrFail($featureKey);

        [$type, $id, $start, $end] = $this->usageContext($billable, $feature);

        $usage = FeatureUsage::query()->updateOrCreate(
            $this->usageKeys($type, $id, $feature, $start),
            $this->usageDefaults($end, $value),
        );

        return (int) $usage->used;
    }

    public function increment(mixed $billable, string $featureKey, int $amount = 1): int
    {
        if ( $amount < 0 )
        {
            throw new InvalidArgumentException("Increment amount must be >= 0.");
        }

        $feature = $this->findFeatureOrFail($featureKey);

        [$type, $id, $start, $end] = $this->usageContext($billable, $feature);

        $usage = FeatureUsage::query()->firstOrCreate(
            $this->usageKeys($type, $id, $feature, $start),
            $this->usageDefaults($end, 0),
        );

        $usage->increment('used', $amount);

        return (int) $usage->refresh()->used;
    }

    public function decrement(mixed $billable, string $featureKey, int $amount = 1): int
    {
        if ( $amount < 0 )
        {
            throw new InvalidArgumentException("Decrement amount must be >= 0.");
        }

        $current = $this->used($billable, $featureKey);
        $next = max(0, $current - $amount);

        return $this->set($billable, $featureKey, $next);
    }

    public function clear(mixed $billable, string $featureKey): void
    {
        $feature = $this->findFeatureOrFail($featureKey);
        [$type, $id, $start, $end] = $this->usageContext($billable, $feature);

        $this->usageQuery($type, $id, $feature, $start)->delete();
    }

    public function usageRowForUpdate(mixed $billable, Feature $feature): FeatureUsage
    {
        [$type, $id, $start, $end] = $this->usageContext($billable, $feature);

        // IMPORTANT: lockForUpdate() => nécessite une transaction ouverte
        $usage = $this->usageQuery($type, $id, $feature, $start)
            ->lockForUpdate()
            ->first();

        if ( $usage )
        {
            // S’assure que period_end est à jour si la période change un jour (edge case)
            if ( $usage->period_end !== $end )
            {
                $usage->period_end = $end;
            }

            return $usage;
        }

        // Pas encore de ligne => on la crée “verrouillée” en la sauvegardant dans la transaction
        $usage = new FeatureUsage([
            'usable_type' => $type,
            'usable_id' => $id,
            'feature_id' => $feature->id,
            'period_start' => $start,
            'period_end' => $end,
            'used' => 0,
        ]);

        $usage->save();

        return $usage;
    }

    private function resolveUsable(mixed $billable): array
    {
        // Eloquent Model
        if ( $billable instanceof Model )
        {
            return [$billable->getMorphClass(), (string) $billable->getKey()];
        }

        // Plain object with id
        if ( is_object($billable) && isset($billable->id) )
        {
            return [get_class($billable), (string) $billable->id];
        }

        throw new InvalidArgumentException('Billable must be an Eloquent model or an object with an id property.');
    }

    private function usageContext(mixed $billable, Feature $feature): array
    {
        [$type, $id] = $this->resolveUsable($billable);
        [$start, $end] = $this->periods->forFeature($feature);

        return [$type, $id, $start, $end];
    }

    private function usageQuery(string $type, string $id, Feature $feature, string $start): Builder
    {
        return FeatureUsage::query()
            ->where('usable_type', $type)
            ->where('usable_id', $id)
            ->where('feature_id', $feature->id)
            ->where('period_start', $start);
    }

    private function usageKeys(string $type, string $id, Feature $feature, string $start): array
    {
        return [
            'usable_type' => $type,
            'usable_id' => $id,
            'feature_id' => $feature->id,
            'period_start' => $start,
        ];
    }

    private function usageDefaults(string $end, int $used): array
    {
        return [
            'period_end' => $end,
            'used' => $used,
        ];
    }

    private function findFeature(string $featureKey): ?Feature
    {
        if ( array_key_exists($featureKey, $this->featureCache) )
        {
            return $this->featureCache[$featureKey];
        }

        $feature = Feature::query()->where('key', $featureKey)->first();

        if ( $feature )
        {
            $this->featureCache[$featureKey] = $feature;
        }

        return $feature;
    }

    private function findFeatureOrFail(string $featureKey): Feature
    {
        if ( array_key_exists($featureKey, $this->featureCache) )
        {
            return $this->featureCache[$featureKey];
        }

        $feature = Feature::query()->where('key', $featureKey)->firstOrFail();
        $this->featureCache[$featureKey] = $feature;

        return $feature;
    }
}
