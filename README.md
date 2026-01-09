# Feature Limiter for Laravel

A flexible **plan / feature / quota / usage** system for Laravel applications.  
Supports:

- Plan & Feature management  
- Quotas per plan  
- Usage tracking per billable (User, Tenant, Team, etc.)  
- Unlimited features  
- Storage units (e.g. `500MB`, `1GB`)  
- Pluggable billing providers (Cashier, manual, fake, etc.)  

---

## Installation

```bash
composer require michael-lurquin/feature-limiter
```

Publish the config and run migrations:

```bash
php artisan vendor:publish --tag=feature-limiter-config
php artisan migrate
```

---

## Concepts

| Term | Meaning |
|------|--------|
| **Plan** | A subscription plan (e.g. Starter, Pro) |
| **Feature** | A capability (sites, storage, custom_code, etc.) |
| **Quota** | The limit defined by the plan |
| **Usage** | How much a billable has consumed |
| **Billable** | Any model or object with an `id` |

---

## Creating Plans

```php
FeatureLimiter::plan('starter')
    ->name('Starter')
    ->sort(0)
    ->active(true)
    ->save();
```

Short version:

```php
FeatureLimiter::plan('starter')
    ->name('Starter')
    ->save();
```

---

## Creating Features

```php
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
```

Short version:

```php
FeatureLimiter::feature('sites')
    ->name('Sites')
    ->type(FeatureType::INTEGER)
    ->save();
```

Supported feature types:

- `FeatureType::INTEGER`
- `FeatureType::BOOLEAN`
- `FeatureType::STORAGE`

---

## Assigning Features to a Plan (Quotas)

### Integer quota

```php
FeatureLimiter::grant('starter')
    ->feature('sites')
    ->quota(3);
```

### Boolean features

```php
FeatureLimiter::grant('starter')->feature('custom_code'); // enabled by default
FeatureLimiter::grant('starter')->feature('custom_code')->enabled();
FeatureLimiter::grant('starter')->feature('library')->disabled();
```

### Unlimited features

```php
FeatureLimiter::grant('pro')->feature('storage')->unlimited();
```

Alternative unlimited values:

```php
->value(null)
->value(-1)
->value('unlimited')
```

---

## Assign Multiple Features at Once

```php
FeatureLimiter::grant('starter')->features([
    'sites' => 3,
    'page' => 30,
    'custom_code' => false,
    'storage' => '1GB',
]);
```

Unlimited:

```php
FeatureLimiter::grant('pro')->features([
    'storage' => 'unlimited',
]);
```

---

## Reading Plan Quotas

```php
FeatureLimiter::viewPlan('starter')->limit('sites');
FeatureLimiter::viewPlan('starter')->enabled('custom_code');
FeatureLimiter::viewPlan('starter')->disabled('custom_code');
FeatureLimiter::viewPlan('pro')->unlimited('storage');
FeatureLimiter::viewPlan('pro')->value('storage');
```

---

## Reading Quotas for a Billable

```php
FeatureLimiter::for($billable)->quota('sites');
FeatureLimiter::for($billable)->enabled('custom_code');
FeatureLimiter::for($billable)->disabled('custom_code');
FeatureLimiter::for($billable)->unlimited('storage');
FeatureLimiter::for($billable)->value('storage');
```

---

## Usage (Consumption Tracking)

```php
FeatureLimiter::for($billable)->usage('sites');
FeatureLimiter::for($billable)->incrementUsage('sites');
FeatureLimiter::for($billable)->decrementUsage('sites');
FeatureLimiter::for($billable)->setUsage('sites', 10);
FeatureLimiter::for($billable)->clearUsage('sites');
```

---

## Quota vs Usage

```php
FeatureLimiter::for($billable)->canConsume('sites', 1);
FeatureLimiter::for($billable)->canConsume('storage', '500MB');
```

Remaining quota:

```php
FeatureLimiter::for($billable)->remainingQuota('sites');
```

Exceeded quota:

```php
FeatureLimiter::for($billable)->exceededQuota('sites', 1);
```

---

## Storage Units

Supported formats:

```
500B
1024KB
500MB
1GB
1.5GB
```

---

## Billing Providers

You can implement your own provider:

```php
interface BillingProvider {
    public function resolvePlanFor(mixed $billable): ?Plan;
    public function pricesFor(Plan $plan): array;
}
```

---

## Billable Objects

A billable can be:

- An Eloquent model  
- Any object with an `id` property  

```php
$billable = new class {
    public int $id = 1;
};
```

---

## License

MIT
