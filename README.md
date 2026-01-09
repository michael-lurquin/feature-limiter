```php
<?php

use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;

// PLAN : Create or Update
FeatureLimiter::plan('starter')
    ->name('Starter')
    ->sort(0)
    ->active(true)
    ->save();
// OR (short)
FeatureLimiter::plan('starter')
    ->name('Starter')
    ->save();


// FEATURE : Create or Update
FeatureLimiter::feature('sites')
    ->name('Sites')
    ->description('Number of sites you can create')
    ->group('create-design')
    ->type(FeatureType::INTEGER)
    ->unit('sites')
    ->reset('none')
    ->sort(0)
    ->active(true)
    ->save();
// OR (short)
FeatureLimiter::feature('sites')
    ->name('Sites')
    ->type(FeatureType::INTEGER)
    ->save();


// Attach feature -> plan
FeatureLimiter::grant('starter')->feature('sites')->quota(3); // 3

FeatureLimiter::grant('starter')->feature('custom_code'); // true
FeatureLimiter::grant('starter')->feature('custom_code')->enabled(); // true

FeatureLimiter::grant('starter')->feature('library')->disabled(); // false

FeatureLimiter::grant('pro')->feature('storage')->unlimited(); // Unlimited
FeatureLimiter::grant('pro')->feature('storage')->value(null); // Unlimited
FeatureLimiter::grant('pro')->feature('storage')->value(-1); // Unlimited
FeatureLimiter::grant('pro')->feature('storage')->value('unlimited'); // Unlimited


// Définir l'ensemble des valeurs de plusieurs features
FeatureLimiter::grant('starter')
    ->features([
        'sites' => 3,
        'page' => 30,
        'custom_code' => false,
        'storage' => '1GB',
    ]);
// OR (short)
FeatureLimiter::grant('pro')
    ->features([
        'storage' => 'unlimited',
    ]);


// Read feature
FeatureLimiter::viewPlan('starter')->limit('sites'); // 3
FeatureLimiter::viewPlan('starter')->enabled('custom_code'); // false|true
FeatureLimiter::viewPlan('starter')->disabled('custom_code'); // false|true
FeatureLimiter::viewPlan('pro')->unlimited('storage'); // false|true
FeatureLimiter::viewPlan('pro')->value('storage'); // 1GB | int | bool | "unlimited"

// Plan quota pour ce billable
FeatureLimiter::for($billable)->quota('sites');
FeatureLimiter::for($billable)->enabled('custom_code');
FeatureLimiter::for($billable)->disabled('custom_code');
FeatureLimiter::for($billable)->unlimited('storage');
FeatureLimiter::for($billable)->value('storage');

// Usage (consommation)
FeatureLimiter::for($billable)->usage('sites'); // valeur de l'usage
FeatureLimiter::for($billable)->incrementUsage('sites'); // valeur de l'usage + 1
FeatureLimiter::for($billable)->decrementUsage('sites'); // valeur de l'usage - 1
FeatureLimiter::for($billable)->setUsage('sites'); // valeur de l'usage écrasée
FeatureLimiter::for($billable)->clearUsage('sites'); // ou supprime la ligne

FeatureLimiter::for($billable)->canConsume('sites', 1);
FeatureLimiter::for($billable)->remainingQuota('sites');
FeatureLimiter::for($billable)->exceededQuota('sites', 1);
FeatureLimiter::for($billable)->canConsume('storage', '500MB');