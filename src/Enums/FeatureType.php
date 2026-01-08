<?php

namespace MichaelLurquin\FeatureLimiter\Enums;

enum FeatureType: string
{
    case BOOLEAN = 'boolean';
    case INTEGER = 'integer';
    case STORAGE = 'storage';
}
