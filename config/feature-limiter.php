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
        'plan_key' => 'free',
    ],

    'billing' => [
        // provider par défaut
        'default' => env('FEATURE_LIMITER_BILLING_PROVIDER', 'cashier'),

        // liste des providers dispo
        'providers' => [
            'cashier' => \MichaelLurquin\FeatureLimiter\Billing\CashierBillingProvider::class,
            // 'paddle' => \...,
            // 'manual' => \MichaelLurquin\FeatureLimiter\Billing\ManualBillingProvider::class,
        ],

        // réglages provider-specific
        'cashier' => [
            'subscription_name' => 'default', // Cashier subscription name
        ],

        // fallback si tu veux un mode sans provider externe
        'manual' => [
            // ex: une colonne sur billable, ou callback, etc.
        ],
    ],
];
