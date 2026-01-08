<?php

namespace MichaelLurquin\FeatureLimiter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    public function getTable()
    {
        return config('feature-limiter.tables.features', 'fl_features');
    }

    protected $fillable = [
        'key',
        'name',
        'group',
        'type',
        'unit',
        'reset_period',
        'description',
        'sort',
        'active',
    ];

    protected $casts = [
        'type' => FeatureType::class,
        'active' => 'boolean',
        'sort' => 'integer',
    ];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, config('feature-limiter.tables.plan_feature', 'fl_feature_plan'), 'feature_id', 'plan_id')
            ->using(PlanFeature::class)
            ->as('planFeature')
            ->withPivot(['value', 'is_unlimited']);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(FeatureUsage::class);
    }
}
