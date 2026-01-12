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

    public function __construct(protected string $planKey, protected string $featureKey) {}

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

    public function unlimited(): Plan
    {
        $this->rawValue = null;
        $this->isUnlimited = true;

        return $this->save();
    }

    public function enabled(bool $enabled = true): Plan
    {
        $this->rawValue = $enabled;
        $this->isUnlimited = false;

        return $this->save();
    }

    public function disabled(): Plan
    {
        return $this->enabled(false);
    }

    /**
     * Value brute: string/int/bool/null, le parser normalise selon FeatureType.
     * $unlimited=true force unlimited (même si value non-null).
     */
    public function value(mixed $value, bool $unlimited = false): Plan
    {
        $this->rawValue = $value;
        $this->isUnlimited = $unlimited;

        return $this->save();
    }

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
