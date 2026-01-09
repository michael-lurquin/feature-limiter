<?php

namespace MichaelLurquin\FeatureLimiter\Repositories;

use Carbon\CarbonImmutable;
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
        $start = $this->normalizeDate($start);
        $end = $this->normalizeDate($end);

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
        $start = $this->normalizeDate($start);
        $end = $this->normalizeDate($end);

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
        $start = $this->normalizeDate($start);
        $end = $this->normalizeDate($end);

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
        $start = $this->normalizeDate($start);
        $end = $this->normalizeDate($end);

        FeatureUsage::query()
            ->where('usable_type', $type)
            ->where('usable_id', $id)
            ->where('feature_id', $feature->id)
            ->where('period_start', $start)
            ->delete();
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

    private function normalizeDate(string|\DateTimeInterface $v): string
    {
        return $v instanceof \DateTimeInterface
            ? CarbonImmutable::instance(\DateTime::createFromInterface($v))->toDateString()
            : CarbonImmutable::parse($v)->toDateString();
    }
}
