<?php

namespace MichaelLurquin\FeatureLimiter\Support;

use Carbon\CarbonImmutable;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Enums\ResetPeriod;

class PeriodResolver
{
    public function lifetime(): array
    {
        return [
            CarbonImmutable::create(1970, 1, 1)->toDateString(),
            CarbonImmutable::create(9999, 12, 31)->toDateString(),
        ];
    }

    public function forFeature(Feature $feature, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        $period = $this->normalizeResetPeriod($feature->reset_period);

        return match ($period) {
            'daily' => [
                $now->startOfDay()->toDateString(),
                $now->endOfDay()->toDateString(),
            ],
            'weekly' => [
                $now->startOfWeek()->toDateString(),
                $now->endOfWeek()->toDateString(),
            ],
            'monthly' => [
                $now->startOfMonth()->toDateString(),
                $now->endOfMonth()->toDateString(),
            ],
            'yearly' => [
                $now->startOfYear()->toDateString(),
                $now->endOfYear()->toDateString(),
            ],
            'none' => $this->lifetime(),
            default => $this->lifetime(),
        };
    }

    private function normalizeResetPeriod(mixed $value): string
    {
        // Enum cast
        if ( $value instanceof ResetPeriod )
        {
            return $value->value; // 'none'|'daily'|'monthly'|'yearly'
        }

        // Raw string in DB
        if ( is_string($value) && $value !== '' )
        {
            return strtolower(trim($value));
        }

        // Fallback config
        return strtolower((string) config('feature-limiter.defaults.reset_period', 'none'));
    }
}
