<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Builders\Concerns\ResolvesPlanAndFeatures;
use MichaelLurquin\FeatureLimiter\Builders\Concerns\UsesFeatureValueParser;

class GrantFeatureBuilder
{
    use ResolvesPlanAndFeatures;
    use UsesFeatureValueParser;

    protected mixed $rawValue = null;
    protected bool $isUnlimited = false;

    /**
     * Create a new grant feature builder to assign a feature to a plan.
     *
     * @param string $planKey Plan identifier (e.g. 'starter', 'pro')
     * @param string $featureKey Feature identifier (e.g. 'sites', 'storage')
     */
    public function __construct(protected string $planKey, protected string $featureKey) {}

    /**
     * Set a numeric quota limit for an INTEGER feature.
     *
     * @param int $quota Number of units (e.g. 3 for "3 sites")
     * @return Plan The updated Plan model instance
     * @throws InvalidArgumentException If quota is negative
     *
     * @example
     * FeatureLimiter::grant('starter')->feature('sites')->quota(3);
     */
    public function quota(int $quota): Plan
    {
        if ( $quota < 0 )
        {
            throw new InvalidArgumentException("Quota must be >= 0.");
        }

        $this->rawValue = $quota;
        $this->isUnlimited = false;

        return $this->save();
    }

    /**
     * Mark a feature as unlimited in this plan.
     *
     * Works for both INTEGER and STORAGE features.
     *
     * @return Plan The updated Plan model instance
     *
     * @example
     * FeatureLimiter::grant('pro')->feature('storage')->unlimited();
     */
    public function unlimited(): Plan
    {
        $this->rawValue = null;
        $this->isUnlimited = true;

        return $this->save();
    }

    /**
     * Enable (or optionally disable) a BOOLEAN feature.
     *
     * @param bool $enabled Default: true to enable, false to disable
     * @return Plan The updated Plan model instance
     *
     * @example
     * FeatureLimiter::grant('starter')->feature('custom_code')->enabled();
     * FeatureLimiter::grant('starter')->feature('api_access')->enabled(false);
     */
    public function enabled(bool $enabled = true): Plan
    {
        $this->rawValue = $enabled;
        $this->isUnlimited = false;

        return $this->save();
    }

    /**
     * Disable a BOOLEAN feature (convenience alias for enabled(false)).
     *
     * @return Plan The updated Plan model instance
     *
     * @example
     * FeatureLimiter::grant('starter')->feature('custom_code')->disabled();
     */
    public function disabled(): Plan
    {
        return $this->enabled(false);
    }

    /**
     * Set a raw value for the feature, automatically normalized by type.
     *
     * Accepts mixed types that are parsed according to the feature type:
     * - INTEGER: numeric values (string "5" converted to int)
     * - STORAGE: string formats like "1GB", "500MB"
     * - BOOLEAN: boolean values or 1/0
     *
     * @param mixed $value Raw value (type conversion handled automatically)
     * @param bool $unlimited Optional: force unlimited even if value is set (default: false)
     * @return Plan The updated Plan model instance
     *
     * @example
     * FeatureLimiter::grant('starter')->feature('storage')->value('1GB');
     * FeatureLimiter::grant('starter')->feature('sites')->value('10');
     */
    public function value(mixed $value, bool $unlimited = false): Plan
    {
        $this->rawValue = $value;
        $this->isUnlimited = $unlimited;

        return $this->save();
    }

    /**
     * Save the feature assignment to the plan.
     *
     * Normalizes the value according to feature type and persists to the database.
     *
     * @return Plan The updated Plan model instance (refreshed from database)
     */
    public function save(): Plan
    {
        $plan = $this->requirePlan($this->planKey);
        $feature = $this->requireFeature($this->featureKey);

        [$value, $isUnlimited] = $this->featureValueParser()->parse($feature, $this->rawValue, $this->isUnlimited);

        $plan->features()->syncWithoutDetaching([
            $feature->id => [
                'value' => $value,
                'is_unlimited' => $isUnlimited,
            ],
        ]);

        return $plan->refresh();
    }
}
