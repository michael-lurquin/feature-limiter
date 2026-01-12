<?php

namespace MichaelLurquin\FeatureLimiter\Builders;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Support\FeatureValueParser;

class GrantFeatureBuilder
{
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
        $plan = Plan::query()->where('key', $this->planKey)->first();

        if ( !$plan )
        {
            throw new InvalidArgumentException("Plan not found: {$this->planKey}");
        }

        $feature = Feature::query()->where('key', $this->featureKey)->first();

        if ( !$feature )
        {
            throw new InvalidArgumentException("Feature not found: {$this->featureKey}");
        }

        $parser = new FeatureValueParser();

        [$value, $isUnlimited] = $parser->parse($feature, $this->rawValue, $this->isUnlimited);

        $plan->features()->syncWithoutDetaching([
            $feature->id => [
                'value' => $value,
                'is_unlimited' => $isUnlimited,
            ],
        ]);

        return $plan->refresh();
    }
}
