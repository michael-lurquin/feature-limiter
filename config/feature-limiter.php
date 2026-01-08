<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    |
    | Override table names if you need to avoid conflicts or follow a naming
    | convention in your application.
    |
    */

    'tables' => [
        'features'      => 'feature_definitions',
        'plans'         => 'plan_definitions',
        'plan_features' => 'plan_feature_values',
        'usages'        => 'feature_usages',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default behavior
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'reset_period' => 'none', // none|daily|monthly|yearly
    ],
];
