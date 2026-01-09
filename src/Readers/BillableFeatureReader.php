<?php

namespace MichaelLurquin\FeatureLimiter\Readers;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Support\Storage;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Billing\BillingManager;
use MichaelLurquin\FeatureLimiter\Repositories\FeatureUsageRepository;

class BillableFeatureReader
{
    protected ?string $providerName = null;

    public function __construct(protected mixed $billable, protected BillingManager $billing, protected FeatureUsageRepository $usages) {}

    public function using(?string $providerName): self
    {
        $this->providerName = $providerName;

        return $this;
    }

    public function plan(): ?Plan
    {
        return $this->billing->provider($this->providerName)->resolvePlanFor($this->billable);
    }

    protected function planOrFail(): Plan
    {
        $plan = $this->plan();

        if ( !$plan )
        {
            $type = is_object($this->billable) ? get_class($this->billable) : gettype($this->billable);

            throw new InvalidArgumentException("No plan resolved for billable: {$type}");
        }

        return $plan;
    }

    // Plan (quota)
    public function quota(string $featureKey): int|string|null
    {
        $plan = $this->planOrFail();

        return (new PlanFeatureReader($plan))->quota($featureKey);
    }

    public function enabled(string $featureKey): bool
    {
        $plan = $this->planOrFail();

        return (new PlanFeatureReader($plan))->enabled($featureKey);
    }

    public function disabled(string $featureKey): bool
    {
        return !$this->enabled($featureKey);
    }

    public function unlimited(string $featureKey): bool
    {
        $plan = $this->planOrFail();

        return (new PlanFeatureReader($plan))->unlimited($featureKey);
    }

    public function value(string $featureKey): mixed
    {
        $plan = $this->planOrFail();

        return (new PlanFeatureReader($plan))->value($featureKey);
    }

    // Usage (consommation)
    public function usage(string $featureKey): int
    {
        return $this->usages->used($this->billable, $featureKey);
    }

    public function setUsage(string $featureKey, int $value): int
    {
        return $this->usages->set($this->billable, $featureKey, $value);
    }

    public function incrementUsage(string $featureKey, int $amount = 1): int
    {
        return $this->usages->increment($this->billable, $featureKey, $amount);
    }

    public function decrementUsage(string $featureKey, int $amount = 1): int
    {
        return $this->usages->decrement($this->billable, $featureKey, $amount);
    }

    public function clearUsage(string $featureKey): void
    {
        $this->usages->clear($this->billable, $featureKey);
    }

    // Quota (plan - usage)
    public function raw(string $featureKey): ?Feature
    {
        $plan = $this->planOrFail();

        if ( !$plan )
        {
            return null; // pas de plan => pas de feature => null
        }

        return $plan->features()->where('key', $featureKey)->first();
    }

    /**
     * remainingQuota:
     * - BOOLEAN: 1 or 0 (enabled/disabled)
     * - INTEGER: int remaining
     * - STORAGE: string remaining (e.g. "512MB") OR "unlimited"
     * - Not found: null
     */
    public function remainingQuota(string $featureKey): int|string|null
    {
        $feature = $this->raw($featureKey);

        if ( !$feature )
        {
            return null;
        }

        // Unlimited => toujours "unlimited"
        if ( $feature->planFeature?->is_unlimited )
        {
            return 'unlimited';
        }

        return match ($feature->type) {
            FeatureType::BOOLEAN => $this->enabled($featureKey) ? 1 : 0,
            FeatureType::INTEGER => $this->remainingIntegerQuota($featureKey),
            FeatureType::STORAGE => $this->remainingStorageQuota($featureKey),
        };
    }

    /**
     * canConsume compares "amount" against remaining quota.
     * - Unlimited => true
     * - BOOLEAN => enabled() (amount is ignored except amount=0)
     * - INTEGER => remaining >= amount
     * - STORAGE => remainingBytes >= needBytes
     */
    public function canConsume(string $featureKey, int|string $amount = 1): bool
    {
        $feature = $this->raw($featureKey);

        if ( !$feature )
        {
            return false;
        }

        if ( $feature->planFeature?->is_unlimited )
        {
            return true;
        }

        return match ($feature->type) {
            FeatureType::BOOLEAN => $this->canConsumeBoolean($featureKey, $amount),
            FeatureType::INTEGER => $this->canConsumeInteger($featureKey, $amount),
            FeatureType::STORAGE => $this->canConsumeStorage($featureKey, $amount),
        };
    }

    public function exceededQuota(string $featureKey, int|string $amount = 1): bool
    {
        return !$this->canConsume($featureKey, $amount);
    }

    private function remainingIntegerQuota(string $featureKey): int
    {
        $quota = $this->quota($featureKey);

        if ( $quota === null ) return 0;

        if ( $quota === 'unlimited' ) return PHP_INT_MAX;

        if ( !is_int($quota) )
        {
            return 0;
        }

        $used = $this->usage($featureKey);

        return max(0, $quota - $used);
    }

    private function remainingStorageQuota(string $featureKey): string
    {
        $quota = $this->quota($featureKey);

        if ( $quota === null ) return '0B';

        if ( $quota === 'unlimited' ) return 'unlimited';

        if ( !is_string($quota) ) return '0B';

        $quotaBytes = Storage::toBytes($quota);
        $usedBytes  = $this->usage($featureKey); // bytes

        $remaining = max(0, $quotaBytes - $usedBytes);

        return Storage::fromBytes($remaining);
    }

    private function canConsumeBoolean(string $featureKey, int|string $amount): bool
    {
        if ( is_int($amount) && $amount === 0 ) return true;

        if ( is_string($amount) && trim($amount) === '0' ) return true;

        return $this->enabled($featureKey);
    }

    private function canConsumeInteger(string $featureKey, int|string $amount): bool
    {
        $remaining = $this->remainingQuota($featureKey);

        if ( $remaining === 'unlimited' ) return true;

        if ( !is_int($remaining) ) return false;

        $n = is_int($amount) ? $amount : ( ctype_digit(trim((string)$amount) ) ? (int) trim((string)$amount) : null );

        if ( $n === null || $n < 0 ) return false;

        return $n <= $remaining;
    }

    private function canConsumeStorage(string $featureKey, int|string $amount): bool
    {
        $feature = $this->raw($featureKey);

        if ( !$feature ) return false;

        if ( $feature->planFeature?->is_unlimited ) return true;

        $quota = $this->quota($featureKey);

        if ( $quota === 'unlimited' ) return true;

        if ( !is_string($quota) ) return false;

        try
        {
            $quotaBytes = Storage::toBytes($quota);
            $usedBytes  = $this->usage($featureKey);

            $remainingBytes = max(0, $quotaBytes - $usedBytes);

            $needBytes = is_int($amount) ? $amount : Storage::toBytes((string) $amount);

            if ( $needBytes < 0 ) return false;

            return $needBytes <= $remainingBytes;
        }
        catch (\Throwable)
        {
            return false;
        }
    }
}
