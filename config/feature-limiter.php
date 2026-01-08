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
        'features'      => 'fl_features',
        'plans'         => 'fl_plans',
        'plan_feature'  => 'fl_feature_plan',
        'usages'        => 'fl_feature_usages',
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
