<?php

namespace MichaelLurquin\FeatureLimiter\Enums;

enum ResetPeriod: string
{
    case NONE = 'none';
    case DAILY = 'daily';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
}