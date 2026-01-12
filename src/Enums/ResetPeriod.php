<?php

namespace MichaelLurquin\FeatureLimiter\Enums;

use InvalidArgumentException;

enum ResetPeriod: string
{
    case NONE = 'none';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    public static function fromString(string $value): self
    {
        return match ($value) {
            'none' => self::NONE,
            'daily' => self::DAILY,
            'weekly' => self::WEEKLY,
            'monthly' => self::MONTHLY,
            'yearly' => self::YEARLY,
            default => throw new InvalidArgumentException("Invalid reset period: {$value}"),
        };
    }
}