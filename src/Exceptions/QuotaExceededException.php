<?php

namespace MichaelLurquin\FeatureLimiter\Exceptions;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(public readonly string $featureKey, public readonly int|string $amount, public readonly int|string|null $remaining)
    {
        parent::__construct("Quota exceeded for feature '{$featureKey}'.");
    }
}
