<?php

namespace MichaelLurquin\FeatureLimiter\Readers;

use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Support\Storage;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Billing\BillingManager;
use MichaelLurquin\FeatureLimiter\Support\UsageAmountParser;
use MichaelLurquin\FeatureLimiter\Exceptions\QuotaExceededException;
use MichaelLurquin\FeatureLimiter\Repositories\FeatureUsageRepository;

class BillableFeatureReader
{
    protected ?string $providerName = null;
    private ?Plan $resolvedPlan = null;
    private bool $planResolved = false;
    private ?PlanFeatureReader $planReader = null;
    private array $featureModelCache = [];
    private array $featureRawCache = [];
    private ?UsageAmountParser $amountParser = null;

    /**
     * Create a new billable feature reader for tracking usage and quotas.
     *
     * @param mixed $billable Any object or Eloquent model with an `id` property (User, Team, Tenant, etc.)
     * @param BillingManager $billing Billing provider manager for resolving plans
     * @param FeatureUsageRepository $usages Usage repository for persisting consumption data
     */
    public function __construct(protected mixed $billable, protected BillingManager $billing, protected FeatureUsageRepository $usages) {}

    /**
     * Override the billing provider for this billable.
     *
     * By default, uses the configured default provider. Call this to use a specific provider.
     *
     * @param string|null $providerName Provider name (must be configured), or null to reset to default
     * @return self
     *
     * @example
     * FeatureLimiter::for($user)->using('cashier')->plan();
     */
    public function using(?string $providerName): self
    {
        $this->providerName = $providerName;
        $this->resolvedPlan = null;
        $this->planResolved = false;
        $this->planReader = null;
        $this->featureModelCache = [];
        $this->featureRawCache = [];

        return $this;
    }

    /**
     * Resolve and return the billable's current subscription plan.
     *
     * Determined by the configured billing provider (e.g. Cashier/Stripe, custom, or fake).
     * Returns null if no plan is assigned.
     *
     * @return Plan|null The billable's current plan or null
     *
     * @example
     * $plan = FeatureLimiter::for($user)->plan();
     * echo $plan->key; // 'starter', 'pro', etc.
     */
    public function plan(): ?Plan
    {
        return $this->getPlan();
    }

    protected function planOrFail(): Plan
    {
        return $this->planOrFailCached();
    }

    protected function planFeatureReader(): PlanFeatureReader
    {
        return $this->planReader();
    }

    private function getPlan(): ?Plan
    {
        if ( $this->planResolved )
        {
            return $this->resolvedPlan;
        }

        $this->resolvedPlan = $this->billing->provider($this->providerName)->resolvePlanFor($this->billable);
        $this->planResolved = true;

        return $this->resolvedPlan;
    }

    private function planOrFailCached(): Plan
    {
        $plan = $this->getPlan();

        if ( !$plan )
        {
            $type = is_object($this->billable) ? get_class($this->billable) : gettype($this->billable);

            throw new InvalidArgumentException("No plan resolved for billable: {$type}");
        }

        return $plan;
    }

    private function planReader(): PlanFeatureReader
    {
        if ( $this->planReader )
        {
            return $this->planReader;
        }

        $this->planReader = new PlanFeatureReader($this->planOrFailCached());

        return $this->planReader;
    }

    private function amountParser(): UsageAmountParser
    {
        if ( $this->amountParser )
        {
            return $this->amountParser;
        }

        $this->amountParser = new UsageAmountParser();

        return $this->amountParser;
    }

    /**
     * Get the quota limit assigned to a feature in the billable's plan.
     *
     * @param string $featureKey Feature identifier (e.g. 'sites', 'storage')
     * @return int|string|null The quota limit, 'unlimited', or null if not assigned
     *
     * @see PlanFeatureReader::quota()
     */
    public function quota(string $featureKey): int|string|null
    {
        return $this->planFeatureReader()->quota($featureKey);
    }

    /**
     * Check if a BOOLEAN feature is enabled in the billable's plan.
     *
     * @param string $featureKey Feature identifier (e.g. 'custom_code', 'api_access')
     * @return bool True if enabled, false otherwise
     */
    public function enabled(string $featureKey): bool
    {
        $feature = $this->refreshFeatureCache($featureKey);

        if ( !$feature )
        {
            return false;
        }

        if ( $feature->type !== FeatureType::BOOLEAN )
        {
            return false;
        }

        return $feature->planFeature?->value === '1';
    }

    /**
     * Check if a BOOLEAN feature is disabled (inverse of enabled()).
     *
     * @param string $featureKey Feature identifier
     * @return bool True if disabled, false otherwise
     */
    public function disabled(string $featureKey): bool
    {
        return !$this->enabled($featureKey);
    }

    /**
     * Check if a feature is unlimited in the billable's plan.
     *
     * @param string $featureKey Feature identifier (e.g. 'storage', 'api_calls')
     * @return bool True if unlimited, false otherwise
     */
    public function unlimited(string $featureKey): bool
    {
        return $this->planFeatureReader()->unlimited($featureKey);
    }

    /**
     * Get the assigned value of a feature, formatted according to its type.
     *
     * @param string $featureKey Feature identifier (e.g. 'sites', 'storage', 'custom_code')
     * @return mixed The feature value formatted per its type, or null if not assigned
     *
     * @see PlanFeatureReader::value()
     */
    public function value(string $featureKey): mixed
    {
        return $this->planFeatureReader()->value($featureKey);
    }

    /**
     * Get the current usage amount for a feature consumed by this billable.
     *
     * Returns usage in appropriate units: integers for INTEGER features, bytes for STORAGE.
     *
     * @param string $featureKey Feature identifier (e.g. 'sites', 'storage')
     * @return int Current usage in units (0 if never used)
     */
    public function usage(string $featureKey): int
    {
        return $this->usages->used($this->billable, $featureKey);
    }

    /**
     * Set the usage to a specific value for a feature.
     *
     * This directly replaces the current usage (no validation against quota).
     *
     * @param string $featureKey Feature identifier
     * @param int $value New usage value (0 or higher)
     * @return int The new usage value
     */
    public function setUsage(string $featureKey, int $value): int
    {
        return $this->usages->set($this->billable, $featureKey, $value);
    }

    /**
     * Increment usage by a specified amount.
     *
     * Does not check quota; use `consume()` for quota-aware consumption.
     *
     * @param string $featureKey Feature identifier
     * @param int $amount Amount to increment by (default: 1)
     * @return int The new usage value
     */
    public function incrementUsage(string $featureKey, int $amount = 1): int
    {
        return $this->usages->increment($this->billable, $featureKey, $amount);
    }

    /**
     * Decrement usage by a specified amount.
     *
     * Usage is clamped to 0 (never goes negative).
     *
     * @param string $featureKey Feature identifier
     * @param int $amount Amount to decrement by (default: 1)
     * @return int The new usage value
     */
    public function decrementUsage(string $featureKey, int $amount = 1): int
    {
        return $this->usages->decrement($this->billable, $featureKey, $amount);
    }

    /**
     * Clear usage (set to 0) for a feature.
     *
     * @param string $featureKey Feature identifier
     * @return void
     */
    public function clearUsage(string $featureKey): void
    {
        $this->usages->clear($this->billable, $featureKey);
    }

    /**
     * Get the raw Feature model (internal method, prefer high-level APIs).
     *
     * @param string $featureKey Feature identifier
     * @return Feature|null The Feature model from the plan or null
     */
    public function raw(string $featureKey): ?Feature
    {
        if ( array_key_exists($featureKey, $this->featureModelCache) )
        {
            return $this->featureModelCache[$featureKey];
        }

        $plan = $this->planOrFailCached();

        $feature = $plan->features()->where('key', $featureKey)->first();
        $this->featureModelCache[$featureKey] = $feature;

        return $feature;
    }

    /**
     * Get remaining quota available before hitting the limit.
     *
     * Returns in appropriate units per feature type:
     * - BOOLEAN: 1 (enabled) or 0 (disabled)
     * - INTEGER: remaining count
     * - STORAGE: remaining formatted string (e.g. "512MB") or 'unlimited'
     *
     * @param string $featureKey Feature identifier
     * @return int|string|null Remaining quota in units, 'unlimited', or null if not assigned
     *
     * @example
     * FeatureLimiter::for($user)->remainingQuota('sites');    // Returns: 1 (out of 3)
     * FeatureLimiter::for($user)->remainingQuota('storage');  // Returns: '512MB'
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
     * Check if enough quota is available to consume the specified amount (without consuming).
     *
     * For unlimited features, always returns true. Does not modify usage.
     *
     * @param string $featureKey Feature identifier
     * @param int|string $amount Amount to check (in units for INTEGER, storage format for STORAGE)
     * @return bool True if consumption would be allowed, false otherwise
     *
     * @example
     * if (FeatureLimiter::for($user)->canConsume('sites', 1)) {
     *     FeatureLimiter::for($user)->consume('sites', 1);
     * }
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

    /**
     * Check if consumption would exceed quota.
     *
     * Inverse of `canConsume()`. Does not modify usage.
     *
     * @param string $featureKey Feature identifier
     * @param int|string $amount Amount to check (default: 1)
     * @return bool True if quota would be exceeded, false otherwise
     */
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

        $n = $this->amountParser()->parsePositiveInt($amount);

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

            $needBytes = $this->amountParser()->parsePositiveBytes($amount);

            if ( $needBytes === null || $needBytes < 0 ) return false;

            return $needBytes <= $remainingBytes;
        }
        catch (\Throwable)
        {
            return false;
        }
    }

    /**
     * Consume quota for a feature (transaction-safe, locks usage row).
     *
     * In non-strict mode (`$strict=false`): returns false if quota exceeded, usage unchanged.
     * In strict mode (`$strict=true`): throws QuotaExceededException if quota exceeded.
     *
     * This method is transactional: either fully succeeds or fully fails.
     *
     * @param string $featureKey Feature identifier
     * @param int|string $amount Amount to consume (units for INTEGER, format for STORAGE)
     * @param bool $strict Default: false. If true, throws on quota exceeded instead of returning false
     * @return int|false New usage value on success, or false if failed (non-strict only)
     * @throws QuotaExceededException When strict=true and quota would be exceeded
     *
     * @example
     * // Non-strict: handle failure gracefully
     * if ($result = FeatureLimiter::for($user)->consume('sites', 1)) {
     *     echo "Consumed. New usage: $result";
     * } else {
     *     echo "Quota exceeded";
     * }
     *
     * // Strict: let exception bubble up
     * $result = FeatureLimiter::for($user)->consume('sites', 1, strict: true);
     */
    public function consume(string $featureKey, int|string $amount = 1, bool $strict = false): int|false
    {
        return $this->consumeUsage($featureKey, $amount, $strict);
    }

    /**
     * Consume quota or throw an exception (strict mode shorthand).
     *
     * Throws QuotaExceededException if quota would be exceeded or feature not found.
     *
     * @param string $featureKey Feature identifier
     * @param int|string $amount Amount to consume (default: 1)
     * @return int New usage value
     * @throws QuotaExceededException If quota exceeded or feature not found
     *
     * @example
     * try {
     *     $newUsage = FeatureLimiter::for($user)->consumeOrFail('sites', 1);
     * } catch (QuotaExceededException $e) {
     *     echo "Quota exceeded: " . $e->getMessage();
     * }
     */
    public function consumeOrFail(string $featureKey, int|string $amount = 1): int
    {
        $res = $this->consumeUsage($featureKey, $amount, strict: true);

        // strict=true => devrait soit throw, soit retourner int
        return (int) $res;
    }

    /**
     * Consume multiple features atomically (all-or-nothing).
     *
     * Either ALL features are consumed, or NONE are. If any feature's quota is exceeded,
     * the entire operation fails and no changes are made.
     *
     * In non-strict mode: returns false on failure, array of new usages on success.
     * In strict mode: throws QuotaExceededException on failure.
     *
     * @param array<string, int|string> $map Feature key => amount mapping
     * @param bool $strict Default: false. If true, throws on any failure
     * @return array<string, int>|false Array of feature => new_usage on success, or false (non-strict only)
     * @throws QuotaExceededException When strict=true and any quota would be exceeded
     *
     * @example
     * $result = FeatureLimiter::for($user)->consumeMany([
     *     'sites' => 1,
     *     'storage' => '500MB',
     * ]);
     * // Returns: ['sites' => 1, 'storage' => 524288000] or false if any fails
     */
    public function consumeMany(array $map, bool $strict = false): array|false
    {
        // Normalize / quick no-op: empty map
        if ( empty($map) ) return [];

        // Filter out explicit zero amounts (they're always allowed and do nothing)
        $map = array_filter($map, fn ($v) => !$this->amountParser()->isZeroAmount($v));

        if ( empty($map) ) return [];

        return DB::transaction(function () use ($map, $strict)
        {
            $plan = $this->planOrFail();

            // 1) Load all features from plan in one query (+ pivot)
            $keys = array_keys($map);

            /** @var \Illuminate\Support\Collection<string, Feature> $features */
            $features = $plan->features()
                ->whereIn('key', $keys)
                ->get()
                ->keyBy('key');

            // Missing in plan => fail
            $missing = array_values(array_diff($keys, $features->keys()->all()));
            if ( !empty($missing) )
            {
                $first = $missing[0];

                if ( $strict )
                {
                    throw new QuotaExceededException($first, $map[$first], null);
                }

                return false;
            }

            // 2) Validate + lock usage rows (for non-boolean) + compute deltas
            $lockedUsageRows = []; // featureKey => FeatureUsage
            $currentUsed = []; // featureKey => int
            $deltas = []; // featureKey => int (units or bytes)

            foreach ($map as $featureKey => $amount)
            {
                $feature = $features[$featureKey];

                // BOOLEAN: check enabled state but do not track usage
                if ( $feature->type === FeatureType::BOOLEAN )
                {
                    if ( !$this->enabled($featureKey) )
                    {
                        if ( $strict )
                        {
                            throw new QuotaExceededException($featureKey, $amount, 0);
                        }

                        return false;
                    }

                    continue;
                }

                // Parse amount => delta (units/bytes). If invalid => fail consistently.
                $delta = $this->amountToDeltaOrFail($feature->type, $amount, $featureKey, $strict);
                if ( $delta <= 0 )
                {
                    // If non-strict and invalid parsing produced 0, treat as failure (consistent with canConsume).
                    if ( $strict )
                    {
                        throw new QuotaExceededException($featureKey, $amount, $this->remainingQuota($featureKey));
                    }

                    return false;
                }

                $deltas[$featureKey] = $delta;

                // Unlimited: allowed; we *can* still track usage, but locking is still required to be safe
                // (keeps usage consistent under concurrency)
                $usageRow = $this->usages->usageRowForUpdate($this->billable, $feature);
                $lockedUsageRows[$featureKey] = $usageRow;
                $currentUsed[$featureKey] = (int) $usageRow->used;

                if ( $feature->planFeature?->is_unlimited )
                {
                    // Always ok, no quota check
                    continue;
                }

                // Quota must exist
                $quotaRaw = $feature->planFeature?->value;
                if ( $quotaRaw === null )
                {
                    if ( $strict )
                    {
                        throw new QuotaExceededException($featureKey, $amount, 0);
                    }

                    return false;
                }

                // Compute remaining and validate
                if ( $feature->type === FeatureType::INTEGER )
                {
                    $quota = (int) $quotaRaw;
                    $remaining = max(0, $quota - $currentUsed[$featureKey]);

                    if ( $delta > $remaining )
                    {
                        if ( $strict )
                        {
                            throw new QuotaExceededException($featureKey, $amount, $remaining);
                        }

                        return false;
                    }

                    continue;
                }

                if ( $feature->type === FeatureType::STORAGE )
                {
                    try
                    {
                        $quotaBytes = Storage::toBytes((string) $quotaRaw);
                    }
                    catch (\Throwable)
                    {
                        if ( $strict )
                        {
                            throw new QuotaExceededException($featureKey, $amount, null);
                        }

                        return false;
                    }

                    $remainingBytes = max(0, $quotaBytes - $currentUsed[$featureKey]);

                    if ( $delta > $remainingBytes )
                    {
                        if ( $strict )
                        {
                            throw new QuotaExceededException($featureKey, $amount, Storage::fromBytes($remainingBytes));
                        }

                        return false;
                    }

                    continue;
                }

                // Unknown type => fail
                if ( $strict )
                {
                    throw new QuotaExceededException($featureKey, $amount, null);
                }

                return false;
            }

            // 3) Apply all increments now (all-or-nothing)
            $result = [];

            foreach ($deltas as $featureKey => $delta)
            {
                $row = $lockedUsageRows[$featureKey];
                $row->used = (int) $row->used + $delta;
                $row->save();

                $result[$featureKey] = (int) $row->used;
            }

            // BOOLEAN entries: return current usage (usually 0) if you want them in the output
            // If you prefer excluding booleans, keep as-is.
            foreach ($map as $featureKey => $amount)
            {
                $feature = $features[$featureKey];

                if ( $feature->type === FeatureType::BOOLEAN )
                {
                    $result[$featureKey] = $this->usage($featureKey);
                }
            }

            return $result;
        });
    }

    /**
     * Consume multiple features or throw an exception (strict mode shorthand).
     *
     * @param array<string, int|string> $map Feature key => amount mapping
     * @return array<string, int> Array of feature => new_usage on success
     * @throws QuotaExceededException If any quota would be exceeded
     */
    public function consumeManyOrFail(array $map): array
    {
        $res = $this->consumeMany($map, strict: true);

        return (array) $res;
    }

    /**
     * Like amountToDelta(), but guarantees "invalid amount" is treated as failure.
     */
    private function amountToDeltaOrFail(FeatureType $type, int|string $amount, string $featureKey, bool $strict): int
    {
        $delta = $this->amountParser()->toDelta($type, $amount);

        if ( $delta === null )
        {
            if ( $strict )
            {
                throw new QuotaExceededException($featureKey, $amount, null);
            }

            return 0;
        }

        return $delta;
    }

    /**
     * Transaction-safe consume.
     *
     * - Locks the current usage row (SELECT ... FOR UPDATE)
     * - Recomputes remaining quota inside the transaction
     * - Increments usage only if allowed
     *
     * Returns:
     * - int: new used value
     * - false: if not allowed (when $strict = false)
     *
     * @throws QuotaExceededException when $strict = true and quota exceeded / invalid amount
     */
    public function consumeUsage(string $featureKey, int|string $amount = 1, bool $strict = false): int|false
    {
        // amount=0 => no-op
        if ( $this->amountParser()->isZeroAmount($amount) )
        {
            return $this->usage($featureKey);
        }

        return DB::transaction(function () use ($featureKey, $amount, $strict)
        {
            $plan = $this->planOrFail();

            /** @var Feature|null $feature */
            $feature = $plan->features()->where('key', $featureKey)->first();

            if ( !$feature )
            {
                if ( $strict )
                {
                    throw new QuotaExceededException($featureKey, $amount, null);
                }

                return false;
            }

            // Boolean: cannot be consumed (not usage-tracked)
            if ( $feature->type === FeatureType::BOOLEAN )
            {
                if ( $strict )
                {
                    throw new QuotaExceededException($featureKey, $amount, 0);
                }

                return false;
            }

            // Unlimited => always allowed; we still track usage for INTEGER/STORAGE
            if ( $feature->planFeature?->is_unlimited )
            {
                $delta = $this->amountToDelta($feature->type, $amount, $strict, $featureKey);

                if ( $delta === null ) return false;

                return $this->usages->increment($this->billable, $featureKey, $delta);
            }

            // Not unlimited => lock usage row and compare remaining
            $usageRow = $this->usages->usageRowForUpdate($this->billable, $feature);
            $currentUsed = (int) $usageRow->used;

            $delta = $this->amountToDelta($feature->type, $amount, $strict, $featureKey);

            if ( $delta === null ) return false;

            // Read quota from plan pivot
            $quotaRaw = $feature->planFeature?->value;

            if ( $quotaRaw === null )
            {
                if ( $strict )
                {
                    throw new QuotaExceededException($featureKey, $amount, 0);
                }

                return false;
            }

            if ( $feature->type === FeatureType::INTEGER )
            {
                $quota = (int) $quotaRaw;
                $remaining = max(0, $quota - $currentUsed);

                if ( $delta > $remaining )
                {
                    if ( $strict )
                    {
                        throw new QuotaExceededException($featureKey, $amount, $remaining);
                    }

                    return false;
                }

                $usageRow->used = $currentUsed + $delta;
                $usageRow->save();

                return (int) $usageRow->used;
            }

            if ( $feature->type === FeatureType::STORAGE )
            {
                // quotaRaw must be string like "1GB"
                try
                {
                    $quotaBytes = Storage::toBytes((string) $quotaRaw);
                }
                catch (\Throwable)
                {
                    if ( $strict )
                    {
                        throw new QuotaExceededException($featureKey, $amount, null);
                    }

                    return false;
                }

                $remainingBytes = max(0, $quotaBytes - $currentUsed);

                if ( $delta > $remainingBytes )
                {
                    if ( $strict )
                    {
                        throw new QuotaExceededException($featureKey, $amount, Storage::fromBytes($remainingBytes));
                    }

                    return false;
                }

                $usageRow->used = $currentUsed + $delta;
                $usageRow->save();

                return (int) $usageRow->used;
            }

            // Fallback (should never happen)
            if ( $strict )
            {
                throw new QuotaExceededException($featureKey, $amount, null);
            }

            return false;
        });
    }

    private function amountToDelta(FeatureType $type, int|string $amount, bool $strict, string $featureKey): ?int
    {
        $delta = $this->amountParser()->toDelta($type, $amount);

        if ( $delta === null )
        {
            if ( $strict )
            {
                throw new QuotaExceededException($featureKey, $amount, null);
            }

            return null;
        }

        return $delta;
    }

    /**
     * Refund (decrement) usage for a feature.
     *
     * Usage is clamped to 0 (never goes negative). Transactional and quota-aware.
     *
     * @param string $featureKey Feature identifier
     * @param int|string $amount Amount to refund (default: 1)
     * @param bool $strict Default: false. If true, throws on invalid feature/amount
     * @return int|false New usage value on success, or false (non-strict only)
     * @throws QuotaExceededException When strict=true and refund would fail
     *
     * @example
     * FeatureLimiter::for($user)->refund('sites', 1);  // Returns: new usage value
     */
    public function refund(string $featureKey, int|string $amount = 1, bool $strict = false): int|false
    {
        return $this->refundUsage($featureKey, $amount, $strict);
    }

    /**
     * Transaction-safe refund (decrement usage, clamped to 0).
     *
     * @return int|false New usage value, or false if failed (non-strict)
     * @throws QuotaExceededException when strict=true and invalid feature/amount
     */
    public function refundUsage(string $featureKey, int|string $amount = 1, bool $strict = false): int|false
    {
        if ( $this->amountParser()->isZeroAmount($amount) )
        {
            return $this->usage($featureKey);
        }

        return DB::transaction(function () use ($featureKey, $amount, $strict)
        {
            $plan = $this->planOrFail();

            /** @var Feature|null $feature */
            $feature = $plan->features()->where('key', $featureKey)->first();

            if ( !$feature )
            {
                if ( $strict )
                {
                    throw new QuotaExceededException($featureKey, $amount, null);
                }

                return false;
            }

            // BOOLEAN: cannot be refunded (not usage-tracked)
            if ( $feature->type === FeatureType::BOOLEAN )
            {
                if ( $strict )
                {
                    throw new QuotaExceededException($featureKey, $amount, 0);
                }

                return false;
            }

            // Parse delta
            $delta = $this->amountToDeltaOrFail($feature->type, $amount, $featureKey, $strict);

            if ( $delta <= 0 )
            {
                if ( $strict )
                {
                    throw new QuotaExceededException($featureKey, $amount, null);
                }

                return false;
            }

            // Lock row
            $usageRow = $this->usages->usageRowForUpdate($this->billable, $feature);
            $current = (int) $usageRow->used;

            $usageRow->used = max(0, $current - $delta);
            $usageRow->save();

            return (int) $usageRow->used;
        });
    }

    /**
     * Refund multiple features atomically (all-or-nothing).
     *
     * If any refund fails, the entire operation fails and no changes are made.
     *
     * @param array<string, int|string> $map Feature key => amount mapping
     * @param bool $strict Default: false. If true, throws on any failure
     * @return array<string, int>|false Array of feature => new_usage on success, or false (non-strict only)
     * @throws QuotaExceededException When strict=true and any refund would fail
     *
     * @example
     * $result = FeatureLimiter::for($user)->refundMany([
     *     'sites' => 1,
     *     'storage' => '500MB',
     * ]);
     */
    public function refundMany(array $map, bool $strict = false): array|false
    {
        if ( empty($map)) return [];

        $map = array_filter($map, fn ($v) => !$this->amountParser()->isZeroAmount($v));

        if ( empty($map) ) return [];

        return DB::transaction(function () use ($map, $strict)
        {
            $plan = $this->planOrFail();

            $keys = array_keys($map);

            $features = $plan->features()
                ->whereIn('key', $keys)
                ->get()
                ->keyBy('key');

            $missing = array_values(array_diff($keys, $features->keys()->all()));

            if ( !empty($missing) )
            {
                $first = $missing[0];

                if ( $strict )
                {
                    throw new QuotaExceededException($first, $map[$first], null);
                }

                return false;
            }

            $deltas = [];
            $rows   = [];

            foreach ($map as $featureKey => $amount)
            {
                $feature = $features[$featureKey];

                if ( $feature->type === FeatureType::BOOLEAN )
                {
                    if ( $strict )
                    {
                        throw new QuotaExceededException($featureKey, $amount, 0);
                    }

                    return false;
                }

                $delta = $this->amountToDeltaOrFail($feature->type, $amount, $featureKey, $strict);

                if ( $delta <= 0 )
                {
                    if ( $strict )
                    {
                        throw new QuotaExceededException($featureKey, $amount, null);
                    }

                    return false;
                }

                $deltas[$featureKey] = $delta;
                $rows[$featureKey]   = $this->usages->usageRowForUpdate($this->billable, $feature);
            }

            $result = [];

            foreach ($deltas as $featureKey => $delta)
            {
                $row = $rows[$featureKey];
                $row->used = max(0, (int)$row->used - $delta);
                $row->save();
                $result[$featureKey] = (int) $row->used;
            }

            // booleans: include if you want (optional)
            foreach ($map as $featureKey => $amount)
            {
                $feature = $features[$featureKey];

                if ( $feature->type === FeatureType::BOOLEAN )
                {
                    $result[$featureKey] = $this->usage($featureKey);
                }
            }

            return $result;
        });
    }

    /**
     * Refund multiple features or throw an exception (strict mode shorthand).
     *
     * @param array<string, int|string> $map Feature key => amount mapping
     * @return array<string, int> Array of feature => new_usage on success
     * @throws QuotaExceededException If any refund would fail
     */
    public function refundManyOrFail(array $map): array
    {
        $res = $this->refundMany($map, strict: true);

        return (array) $res;
    }

    /**
     * Get remaining quota for multiple features in one call.
     *
     * @param array<int|string, mixed> $features List or map of feature keys:
     *        ['sites', 'storage'] or ['sites' => 1, 'storage' => '500MB']
     * @return array<string, int|string|null> Map of feature => remaining quota
     *
     * @example
     * $remaining = FeatureLimiter::for($user)->remainingQuotaMany([
     *     'sites', 'storage', 'custom_code'
     * ]);
     * // Returns: ['sites' => 2, 'storage' => '512MB', 'custom_code' => 1]
     */
    public function remainingQuotaMany(array $features): array
    {
        $out = [];
        $keys = [];

        foreach ($features as $key => $value)
        {
            $featureKey = is_int($key) ? (string) $value : (string) $key;
            $keys[$featureKey] = true;
        }

        $this->primeFeatureModelCache(array_keys($keys));

        foreach ($features as $key => $value)
        {
            // allow passing ['sites', 'storage'] OR ['sites' => 1, 'storage' => '500MB']
            $featureKey = is_int($key) ? (string) $value : (string) $key;

            $out[$featureKey] = $this->remainingQuota($featureKey);
        }

        return $out;
    }

    /**
     * Check if multiple features can be consumed (without consuming).
     *
     * Returns true only if ALL features can be consumed. Useful for pre-flight checks.
     *
     * @param array<int|string, mixed> $features Feature keys and amounts:
     *        ['sites', 'storage'] (default amount=1) or ['sites' => 1, 'storage' => '500MB']
     * @param bool $strict Default: false. If true, throws on first failure
     * @return bool True if ALL features can be consumed, false otherwise (non-strict only)
     * @throws QuotaExceededException When strict=true and any quota check fails
     *
     * @example
     * if (FeatureLimiter::for($user)->canConsumeMany(['sites' => 2, 'storage' => '1GB'])) {
     *     FeatureLimiter::for($user)->consumeMany([...]);
     * }
     */
    public function canConsumeMany(array $features, bool $strict = false): bool
    {
        // Normalize to map: featureKey => aggregated amount
        $map = [];
        $keys = [];

        foreach ($features as $key => $value)
        {
            $featureKey = is_int($key) ? (string) $value : (string) $key;
            $keys[$featureKey] = true;
        }

        $this->primeFeatureRawCache(array_keys($keys));

        foreach ($features as $key => $value)
        {
            // list syntax: ['sites', 'storage'] => amount=1
            if (is_int($key))
            {
                $featureKey = (string) $value;
                $amount = 1;
            }
            else
            {
                $featureKey = (string) $key;
                $amount = $value;
            }

            // Aggregate duplicates:
            // - INTEGER: sum ints
            // - STORAGE: sum bytes
            // - BOOLEAN: keep as 1 (or last), it’s just enabled check
            $raw = $this->rawFeatureData($featureKey);

            if ( !$raw )
            {
                if ($strict)
                {
                    throw new QuotaExceededException($featureKey, $amount, null);
                }
                return false;
            }

            if ( $raw['is_unlimited'] )
            {
                // unlimited always ok; keep just one entry
                $map[$featureKey] = $amount;

                continue;
            }

            if ( $raw['type'] === FeatureType::BOOLEAN )
            {
                $map[$featureKey] = 1;

                continue;
            }

            if ( $raw['type'] === FeatureType::INTEGER )
            {
                $n = $this->amountParser()->parsePositiveInt($amount);

                if ( $n === null || $n < 0 )
                {
                    if ( $strict ) throw new QuotaExceededException($featureKey, $amount, $this->remainingQuota($featureKey));

                    return false;
                }

                $map[$featureKey] = ($map[$featureKey] ?? 0) + $n;

                continue;
            }

            if ( $raw['type'] === FeatureType::STORAGE )
            {
                $bytes = $this->amountParser()->parsePositiveBytes($amount);

                if ( $bytes === null || $bytes < 0 )
                {
                    if ( $strict ) throw new QuotaExceededException($featureKey, $amount, $this->remainingQuota($featureKey));

                    return false;
                }

                $map[$featureKey] = ($map[$featureKey] ?? 0) + $bytes;

                continue;
            }

            if ( $strict ) throw new QuotaExceededException($featureKey, $amount, null);

            return false;
        }

        // Now evaluate aggregated map
        foreach ($map as $featureKey => $amount)
        {
            if ( !$this->canConsume($featureKey, $amount) )
            {
                if ( $strict )
                {
                    throw new QuotaExceededException($featureKey, $amount, $this->remainingQuota($featureKey));
                }

                return false;
            }
        }

        return true;
    }

    private function rawFeatureData(string $featureKey): ?array
    {
        if ( array_key_exists($featureKey, $this->featureRawCache) )
        {
            $cached = $this->featureRawCache[$featureKey];

            if ( is_array($cached) && ($cached['type'] ?? null) === FeatureType::BOOLEAN )
            {
                $this->refreshFeatureCache($featureKey);

                return $this->featureRawCache[$featureKey];
            }

            return $cached;
        }

        $feature = $this->raw($featureKey);

        if ( !$feature )
        {
            $this->featureRawCache[$featureKey] = null;

            return null;
        }

        $raw = [
            'type' => $feature->type,
            'value' => $feature->planFeature?->value,
            'is_unlimited' => (bool) $feature->planFeature?->is_unlimited,
        ];

        $this->featureRawCache[$featureKey] = $raw;

        return $raw;
    }

    private function primeFeatureModelCache(array $keys): void
    {
        $missing = array_values(array_filter($keys, fn ($key) => !array_key_exists($key, $this->featureModelCache)));

        if ( empty($missing) )
        {
            return;
        }

        $plan = $this->planOrFailCached();

        $features = $plan->features()->whereIn('key', $missing)->get();

        foreach ($features as $feature)
        {
            $this->featureModelCache[$feature->key] = $feature;
        }

        $found = $features->pluck('key')->all();

        foreach ($missing as $key)
        {
            if ( !in_array($key, $found, true) )
            {
                $this->featureModelCache[$key] = null;
            }
        }
    }

    private function primeFeatureRawCache(array $keys): void
    {
        $missing = array_values(array_filter($keys, fn ($key) => !array_key_exists($key, $this->featureRawCache)));

        if ( empty($missing) )
        {
            return;
        }

        $plan = $this->planOrFailCached();
        $features = $plan->features()->whereIn('key', $missing)->get();

        foreach ($features as $feature)
        {
            $raw = [
                'type' => $feature->type,
                'value' => $feature->planFeature?->value,
                'is_unlimited' => (bool) $feature->planFeature?->is_unlimited,
            ];
            $this->featureRawCache[$feature->key] = $raw;
            $this->featureModelCache[$feature->key] = $feature;

            if ( $feature->type === FeatureType::BOOLEAN )
            {
                unset($this->featureRawCache[$feature->key]);
                unset($this->featureModelCache[$feature->key]);
            }
        }

        $found = $features->pluck('key')->all();

        foreach ($missing as $key)
        {
            if ( !in_array($key, $found, true) )
            {
                $this->featureRawCache[$key] = null;
                $this->featureModelCache[$key] = null;
            }
        }
    }

    private function refreshFeatureCache(string $featureKey): ?Feature
    {
        $plan = $this->planOrFailCached();
        $feature = $plan->features()->where('key', $featureKey)->first();

        $this->featureModelCache[$featureKey] = $feature;
        $this->featureRawCache[$featureKey] = $feature ? [
            'type' => $feature->type,
            'value' => $feature->planFeature?->value,
            'is_unlimited' => (bool) $feature->planFeature?->is_unlimited,
        ] : null;

        $this->planReader = null;

        return $feature;
    }
}
