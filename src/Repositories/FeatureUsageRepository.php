<?php

namespace MichaelLurquin\FeatureLimiter\Repositories;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Models\FeatureUsage;
use MichaelLurquin\FeatureLimiter\Support\PeriodResolver;

class FeatureUsageRepository
{
    public function __construct(protected PeriodResolver $periods) {}

    public function used(mixed $billable, string $featureKey): int
    {
        $feature = Feature::query()->where('key', $featureKey)->first();

        if ( !$feature )
        {
            throw new InvalidArgumentException("Feature not found: {$featureKey}");
        }

        [$type, $id] = $this->resolveUsable($billable);
        [$start, $end] = $this->periods->forFeature($feature);

        $usage = FeatureUsage::query()
            ->where('usable_type', $type)
            ->where('usable_id', $id)
            ->where('feature_id', $feature->id)
            ->where('period_start', $start)
            ->first();

        return (int) ( $usage?->used ?? 0 );
    }

    public function set(mixed $billable, string $featureKey, int $value): int
    {
        if ( $value < 0 )
        {
            throw new InvalidArgumentException("Usage value must be >= 0.");
        }

        $feature = Feature::query()->where('key', $featureKey)->firstOrFail();

        [$type, $id] = $this->resolveUsable($billable);
        [$start, $end] = $this->periods->forFeature($feature);

        $usage = FeatureUsage::query()->updateOrCreate(
            [
                'usable_type'  => $type,
                'usable_id' => $id,
                'feature_id' => $feature->id,
                'period_start' => $start,
            ],
            [
                'period_end' => $end,
                'used' => $value,
            ]
        );

        return (int) $usage->used;
    }

    public function increment(mixed $billable, string $featureKey, int $amount = 1): int
    {
        if ( $amount < 0 )
        {
            throw new InvalidArgumentException("Increment amount must be >= 0.");
        }

        $feature = Feature::query()->where('key', $featureKey)->firstOrFail();

        [$type, $id] = $this->resolveUsable($billable);
        [$start, $end] = $this->periods->forFeature($feature);

        $usage = FeatureUsage::query()->firstOrCreate(
            [
                'usable_type' => $type,
                'usable_id' => $id,
                'feature_id' => $feature->id,
                'period_start' => $start,
            ],
            [
                'period_end' => $end,
                'used' => 0,
            ],
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
        $feature = Feature::query()->where('key', $featureKey)->firstOrFail();
        [$type, $id] = $this->resolveUsable($billable);
        [$start, $end] = $this->periods->forFeature($feature);

        FeatureUsage::query()
            ->where('usable_type', $type)
            ->where('usable_id', $id)
            ->where('feature_id', $feature->id)
            ->where('period_start', $start)
            ->delete();
    }

    public function usageRowForUpdate(mixed $billable, Feature $feature): FeatureUsage
    {
        [$type, $id] = $this->resolveUsable($billable);
        [$start, $end] = $this->periods->forFeature($feature);

        // IMPORTANT: lockForUpdate() => nécessite une transaction ouverte
        $usage = FeatureUsage::query()
            ->where('usable_type', $type)
            ->where('usable_id', $id)
            ->where('feature_id', $feature->id)
            ->where('period_start', $start)
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
}
