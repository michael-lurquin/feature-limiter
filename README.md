# Feature Limiter for Laravel

A flexible **plan / feature / quota / usage** system for Laravel applications, with built-in helpers for **pricing pages** (cards & comparison tables).

Supports:

- Plan & Feature management
- Quotas per plan
- Usage tracking per billable (User, Tenant, Team, etc.)
- Unlimited features
- Storage units (e.g. `500MB`, `1GB`)
- Period-based resets (daily, weekly, monthly, yearly, lifetime)
- Transaction-safe consumption
- Pluggable billing providers (Cashier, manual, fake, etc.)
- Pricing catalog generation (cards & comparison tables)

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
| **Reset period** | When usage is reset (none, daily, weekly, monthly, yearly) |

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
    ->name('Starter') // Optional (ucfirst on 'key' column : starter => Starter)
    ->save();
```

### Multiple plans:

```php
FeatureLimiter::plans([
    'free' => ['sort' => 0],
    'starter' => ['sort' => 1],
    'comfort' => ['sort' => 2],
    'pro' => ['name' => 'Gold', 'sort' => 3],
    'enterprise' => ['sort' => 4, 'active' => false],
])->save();
```

Sort version:

```php
FeatureLimiter::plans([
    'free',
    'starter',
    'comfort',
    'pro',
    'enterprise',
])->save();
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
    ->reset(ResetPeriod::NONE) // none|daily|weekly|monthly|yearly
    ->sort(0)
    ->active(true)
    ->save();
```

Short version:

```php
FeatureLimiter::feature('sites')
    ->name('Sites') // Optional (ucfirst on 'key' column : sites => Sites)
    ->type(FeatureType::INTEGER)
    ->save();
```

Supported feature types:

- `FeatureType::INTEGER`
- `FeatureType::BOOLEAN`
- `FeatureType::STORAGE`

### Multiple features:

```php
FeatureLimiter::features([
    'sites' => ['sort' => 0, 'type' => FeatureType::INTEGER],
    'storage' => ['sort' => 1, 'type' => FeatureType::STORAGE],
    'custom_code' => ['name' => 'Custom Code', 'type' => FeatureType::BOOLEAN, 'sort' => 2],
])->save();
```

Short version:

```php
FeatureLimiter::features([
    'sites' => ['type' => FeatureType::INTEGER],
    'storage' => ['type' => FeatureType::STORAGE],
    'custom_code' => ['name' => 'Custom Code', 'type' => FeatureType::BOOLEAN],
])->save();
```

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
FeatureLimiter::viewPlan('starter')->quota('sites');
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

### Consuming quota (non-strict)

```php
FeatureLimiter::for($billable)->consume('sites', 1);
// returns false if not enough quota
```

### Consuming quota (strict)

```php
FeatureLimiter::for($billable)->consume('storage', '500MB', strict: true);
// throws QuotaExceededException if quota is exceeded
```

### Convenience aliases

```php
FeatureLimiter::for($billable)->consumeOrFail('sites', 1);
FeatureLimiter::for($billable)->consumeManyOrFail([
    'sites' => 1,
    'storage' => '500MB',
]);
```

### Refund / rollback usage

```php
FeatureLimiter::for($billable)->refund('sites', 1);
FeatureLimiter::for($billable)->refundMany([
    'sites' => 1,
    'storage' => '500MB',
]);
```

---

## Quota vs Usage

Check if a billable can still consume quota:

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

Multiple features at once:

```php
FeatureLimiter::for($billable)->canConsumeMany([
    'sites' => 1,
    'storage' => '500MB',
]);

FeatureLimiter::for($billable)->remainingQuotaMany([
    'sites',
    'storage',
]);
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

## Reset Periods

Each feature can define how often its usage resets:

```php
ResetPeriod::NONE     // lifetime
ResetPeriod::DAILY
ResetPeriod::WEEKLY
ResetPeriod::MONTHLY
ResetPeriod::YEARLY
```

Usage is automatically grouped per period in the database.

---

## Catalog (Pricing UI)

FeatureLimiter can generate ready-to-render structures for your pricing pages.

### Pricing cards (featured features)

```php
$cards = FeatureLimiter::catalog()->plansCards(
    featured: ['sites', 'pages', 'storage', 'custom_code'],
    onlyActivePlans: true,
    provider: 'cashier', // optional
);
```

### Full comparaison table (grouped)

```php
$table = FeatureLimiter::catalog()->comparisonTable(
    onlyActivePlans: true,
    onlyActiveFeatures: true,
    provider: 'cashier', // optional
);
```

The returned structure contains plan headers + grouped feature rows (by feature.group), perfect for building a pricing comparison table.

## Pricing (optional)

FeatureLimiter can optionally fetch and expose plan prices through a billing provider (e.g. Stripe via Cashier).

### 1) Catalog output (cards / comparison table)

By default, catalogs do **not** include prices (no external provider calls):

```php
FeatureLimiter::catalog()->plansCards(['site', 'page', 'storage', 'banner', 'cms', 'collection']);
FeatureLimiter::catalog()->comparisonTable();
```

If you want prices, explicitly enable them:

```php
FeatureLimiter::catalog()
    ->includePrices()
    ->plansCards(['site', 'page', 'storage', 'banner', 'cms', 'collection']);

FeatureLimiter::catalog()
    ->includePrices()
    ->comparisonTable();
```

### 2) Single plan

By default, viewPlan() returns the plan reader without prices:

```php
FeatureLimiter::viewPlan('free');
```

To fetch prices for a specific plan:

```php
FeatureLimiter::viewPlan('free')->prices();
```

### 3) Billable plan

By default, resolving the billable plan does not fetch prices:

```php
FeatureLimiter::for($billable)->plan();
```

To fetch prices for the resolved plan:

```php
FeatureLimiter::for($billable)->plan()->prices();
```

---

## Pruning Old Feature Usages

Over time, the `fl_feature_usages` table can grow significantly.
FeatureLimiter provides a built-in Artisan command to clean up old usage records while keeping recent data for analytics, reporting, and charts.

### Basic usage

Remove all usage records older than **12 months**:

```bash
php artisan feature-limiter:prune-usages --months=12
```

Remove all usage records older than **90 days** (dry run – no deletion):

```bash
php artisan feature-limiter:prune-usages --days=90 --dry-run
```

### Optional flags

| Option | Description |
|--------|-------------|
| `--days=90` | Keep only the last 90 days of usage |
| `--months=12` | Keep only the last 12 months of usage |
| `--years=2` | Keep only the last 2 years of usage |
| `--dry-run` | Show what would be deleted without deleting |
| `--prune-zero` | Also remove rows where `used = 0` |

### Scheduler example

```php
$schedule->command('feature-limiter:prune-usages --months=12')->daily();
```

---

## Why keep historical usages?

FeatureLimiter stores all usage records by default so you can:

- Build usage charts
- Generate reports
- Track growth over time
- Audit consumption behavior

The pruning command gives you **full control** over how much history you keep.

---

## License

MIT

## Contributing

See `CONTRIBUTING.md`.