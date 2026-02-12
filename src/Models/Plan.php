<?php

namespace MichaelLurquin\FeatureLimiter\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MichaelLurquin\FeatureLimiter\Billing\BillingManager;

class Plan extends Model
{
    public function getTable()
    {
        return config('feature-limiter.tables.plans', 'fl_plans');
    }

    protected $fillable = [
        'key',
        'name',
        'description',
        'price_monthly',
        'price_yearly',
        'provider',
        'provider_monthly_id',
        'provider_yearly_id',
        'sort',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort' => 'integer',
    ];

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, config('feature-limiter.tables.plan_feature', 'fl_feature_plan'), 'plan_id', 'feature_id')
            ->using(PlanFeature::class)
            ->as('planFeature')
            ->withPivot(['value', 'is_unlimited']);
    }

    protected function priceMonthly(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => is_null($value) ? null : $value / 100,
            set: fn ($value) => is_null($value) ? null : $this->toCents($value),
        );
    }

    protected function priceYearly(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => is_null($value) ? null : $value / 100,
            set: fn ($value) => is_null($value) ? null : $this->toCents($value),
        );
    }

    private function toCents(int|float|string $value): int
    {
        // Allows : 9.99, "9.99", 2000
        if ( is_string($value) ) $value = str_replace(',', '.', trim($value));

        return (int) round(((float) $value) * 100);
    }

    public function prices(): array
    {
        $provider = $this->provider ?: null;

        $billing = app(BillingManager::class);

        return $billing->provider($provider)->pricesFor($this);
    }
}
