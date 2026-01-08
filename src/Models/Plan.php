<?php

namespace MichaelLurquin\FeatureLimiter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    public function getTable()
    {
        return config('feature-limiter.tables.plans', 'fl_plans');
    }

    protected $fillable = [
        'key',
        'name',
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
}
