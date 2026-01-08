<?php

namespace MichaelLurquin\FeatureLimiter\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Pivot
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

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

    public function valueAsInt(): ?int
    {
        return is_null($this->value) ? null : (int) $this->value;
    }

    public function valueAsBytes(): ?int
    {
        return null;
    }
}
