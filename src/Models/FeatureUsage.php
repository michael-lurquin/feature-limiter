<?php

namespace MichaelLurquin\FeatureLimiter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureUsage extends Model
{
    public function getTable()
    {
        return config('feature-limiter.tables.usages', 'fl_feature_usages');
    }

    protected $fillable = [
        'usable_type',
        'usable_id',
        'feature_id',
        'period_end',
        'used',
    ];

    protected $casts = [
        'used' => 'integer',
        'period_end' => 'date',
    ];

    public function usable(): MorphTo
    {
        return $this->morphTo();
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }
}
