<?php

namespace MichaelLurquin\FeatureLimiter\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Pivot
{
    public $timestamps = false;

    public function getTable()
    {
        return config('feature-limiter.tables.plan_feature', 'fl_feature_plan');
    }

    protected $fillable = [
        'plan_id',
        'feature_id',
        'value',
        'is_unlimited',
    ];

    protected $casts = [
        'is_unlimited' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }
}
