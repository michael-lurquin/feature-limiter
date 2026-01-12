<?php

namespace MichaelLurquin\FeatureLimiter\Support;

use MichaelLurquin\FeatureLimiter\Enums\FeatureType;

class UsageAmountParser
{
    public function toDelta(FeatureType $type, int|string $amount): ?int
    {
        return match ($type) {
            FeatureType::INTEGER => $this->parsePositiveInt($amount),
            FeatureType::STORAGE => $this->parsePositiveBytes($amount),
            default => 0,
        };
    }

    public function parsePositiveInt(int|string $amount): ?int
    {
        if ( is_int($amount) )
        {
            return $amount >= 0 ? $amount : null;
        }

        $s = trim((string) $amount);

        if ( $s === '' ) return null;

        if ( !ctype_digit($s) ) return null;

        return (int) $s;
    }

    public function parsePositiveBytes(int|string $amount): ?int
    {
        try
        {
            $bytes = is_int($amount) ? $amount : Storage::toBytes((string) $amount);
        }
        catch (\Throwable)
        {
            return null;
        }

        return $bytes >= 0 ? $bytes : null;
    }

    public function isZeroAmount(int|string $amount): bool
    {
        if ( is_int($amount) ) return $amount === 0;

        $s = trim((string) $amount);

        $upper = strtoupper($s);

        return $s === '0' || $upper === '0B' || $upper === '0KB' || $upper === '0MB' || $upper === '0GB';
    }
}
