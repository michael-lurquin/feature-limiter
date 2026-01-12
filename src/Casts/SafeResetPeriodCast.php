<?php

namespace MichaelLurquin\FeatureLimiter\Casts;

use MichaelLurquin\FeatureLimiter\Enums\ResetPeriod;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class SafeResetPeriodCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ResetPeriod
    {
        if ( $value instanceof ResetPeriod )
        {
            return $value;
        }

        if ( is_string($value) )
        {
            return ResetPeriod::tryFrom(strtolower(trim($value))) ?? ResetPeriod::NONE;
        }

        return ResetPeriod::NONE;
    }

    public function set($model, string $key, $value, array $attributes): string
    {
        if ( $value instanceof ResetPeriod )
        {
            return $value->value;
        }

        if ( is_string($value) )
        {
            return ResetPeriod::tryFrom(strtolower(trim($value)))?->value ?? ResetPeriod::NONE->value;
        }

        return ResetPeriod::NONE->value;
    }
}
