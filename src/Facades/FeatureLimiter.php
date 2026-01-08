<?php

namespace MichaelLurquin\FeatureLimiter\Facades;

use Illuminate\Support\Facades\Facade;

class FeatureLimiter extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'feature-limiter';
    }
}