<?php

namespace MichaelLurquin\FeatureLimiter\Enums;

use InvalidArgumentException;

enum FeatureType: string
{
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case STORAGE = 'storage';

    public static function fromString(string $value): self
    {
        return match ($value) {
            'boolean' => self::BOOLEAN,
            'integer' => self::INTEGER,
            'storage' => self::STORAGE,
            default => throw new InvalidArgumentException("Invalid feature type: {$value}"),
        };
    }
}
