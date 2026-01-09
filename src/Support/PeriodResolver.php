<?php

namespace MichaelLurquin\FeatureLimiter\Support;

use Carbon\CarbonImmutable;
use MichaelLurquin\FeatureLimiter\Models\Feature;

class PeriodResolver
{
    public function forFeature(Feature $feature): array
    {
        $period = $feature->reset_period ?? 'none';

        return match ($period) {
            'daily' => $this->daily(),
            'monthly' => $this->monthly(),
            'yearly' => $this->yearly(),
            'none', null => $this->lifetime(),
            default => $this->lifetime(), // none
        };
    }

    public function lifetime(): array
    {
        return [
            CarbonImmutable::create(1970, 1, 1)->toDateString(),
            CarbonImmutable::create(9999, 12, 31)->toDateString(),
        ];
    }

    public function daily(): array
    {
        $start = CarbonImmutable::now()->startOfDay();
        $end = CarbonImmutable::now()->endOfDay();

        return [$start->toDateString(), $end->toDateString()];
    }

    public function monthly(): array
    {
        $now = CarbonImmutable::now();

        return [
            $now->startOfMonth()->toDateString(),
            $now->endOfMonth()->toDateString(),
        ];
    }

    public function yearly(): array
    {
        $now = CarbonImmutable::now();

        return [
            $now->startOfYear()->toDateString(),
            $now->endOfYear()->toDateString(),
        ];
    }
}
