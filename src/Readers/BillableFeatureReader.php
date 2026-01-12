<?php

namespace MichaelLurquin\FeatureLimiter\Readers;

use InvalidArgumentException;
use Illuminate\Support\Facades\DB;
use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Support\Storage;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Billing\BillingManager;
use MichaelLurquin\FeatureLimiter\Exceptions\QuotaExceededException;
use MichaelLurquin\FeatureLimiter\Repositories\FeatureUsageRepository;

class BillableFeatureReader
{
    protected ?string $providerName = null;
    protected ?Plan $resolvePlan = null;
    protected ?PlanFeatureReader $planReader = null;

    public function __construct(protected mixed $billable, protected BillingManager $billing, protected FeatureUsageRepository $usages) {}

    public function using(?string $providerName): self
    {
        $this->providerName = $providerName;
        $this->resolvePlan = null;
        $this->planReader = null;

        return $this;
    }

    public function plan(): ?Plan
    {
        return $this->billing->provider($this->providerName)->resolvePlanFor($this->billable);
    }

    protected function planOrFail(): Plan
    {
        if ( $this->resolvePlan ) return $this->resolvePlan;

        $plan = $this->plan();

        if ( !$plan )
        {
            $type = is_object($this->billable) ? get_class($this->billable) : gettype($this->billable);

            throw new InvalidArgumentException("No plan resolved for billable: {$type}");
        }

        $this->resolvePlan = $plan;

        return $plan;
    }

    protected function planFeatureReader(): PlanFeatureReader
    {
        if ( $this->planReader )
        {
            return $this->planReader;
        }

        $this->planReader = new PlanFeatureReader($this->planOrFail());

        return $this->planReader;
    }

    // Plan (quota)
    public function quota(string $featureKey): int|string|null
    {
        return $this->planFeatureReader()->quota($featureKey);
    }

    public function enabled(string $featureKey): bool
    {
        return $this->planFeatureReader()->enabled($featureKey);
    }

    public function disabled(string $featureKey): bool
    {
        return !$this->enabled($featureKey);
    }

    public function unlimited(string $featureKey): bool
    {
        return $this->planFeatureReader()->unlimited($featureKey);
    }

    public function value(string $featureKey): mixed
    {
        return $this->planFeatureReader()->value($featureKey);
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

    public function consume(string $featureKey, int|string $amount = 1, bool $strict = false): int|false
    {
        return $this->consumeUsage($featureKey, $amount, $strict);
    }

    public function consumeOrFail(string $featureKey, int|string $amount = 1): int
    {
        $res = $this->consumeUsage($featureKey, $amount, strict: true);

        // strict=true => devrait soit throw, soit retourner int
        return (int) $res;
    }

    /**
     * Consume multiple features atomically (all-or-nothing).
     *
     * @param array<string, int|string> $map featureKey => amount
     * @return array<string, int>|false  new usages by featureKey (INTEGER units / STORAGE bytes)
     *
     * If $strict=false: returns false on first failure (no change applied).
     * If $strict=true: throws QuotaExceededException on first failure.
     */
    public function consumeMany(array $map, bool $strict = false): array|false
    {
        // Normalize / quick no-op: empty map
        if ( empty($map) ) return [];

        // Filter out explicit zero amounts (they're always allowed and do nothing)
        $map = array_filter($map, fn ($v) => !$this->isZeroAmount($v));

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

                // BOOLEAN: no usage tracking; only "enabled" check (unless amount is zero, already filtered)
                if ( $feature->type === FeatureType::BOOLEAN )
                {
                    if ( !$this->enabled($featureKey ))
                    {
                        if ( $strict )
                        {
                            throw new QuotaExceededException($featureKey, $amount, 0);
                        }

                        return false;
                    }

                    // no delta, no row to lock
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
        if ( $type === FeatureType::INTEGER )
        {
            $n = $this->parsePositiveInt($amount);

            if ( $n === null )
            {
                if ( $strict)
                {
                    throw new QuotaExceededException($featureKey, $amount, null);
                }

                return 0;
            }
            return $n;
        }

        if ( $type === FeatureType::STORAGE )
        {
            $bytes = $this->parsePositiveBytes($amount);

            if ( $bytes === null )
            {
                if ( $strict )
                {
                    throw new QuotaExceededException($featureKey, $amount, null);
                }

                return 0;
            }

            return $bytes;
        }

        return 0;
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
        if ( $this->isZeroAmount($amount) )
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

            // Boolean: no usage tracking by default, just check enabled
            if ( $feature->type === FeatureType::BOOLEAN )
            {
                $allowed = $this->enabled($featureKey) || $this->isZeroAmount($amount);

                if ( !$allowed )
                {
                    if ( $strict )
                    {
                        throw new QuotaExceededException($featureKey, $amount, 0);
                    }

                    return false;
                }

                return $this->usage($featureKey);
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
        if ( $type === FeatureType::INTEGER )
        {
            $n = $this->parsePositiveInt($amount);

            if ( $n === null )
            {
                if ( $strict )
                {
                    throw new QuotaExceededException($featureKey, $amount, null);
                }

                return null;
            }

            return $n;
        }

        if ( $type === FeatureType::STORAGE )
        {
            $bytes = $this->parsePositiveBytes($amount);

            if ( $bytes === null )
            {
                if ( $strict )
                {
                    throw new QuotaExceededException($featureKey, $amount, null);
                }

                return null;
            }

            return $bytes;
        }

        return 0;
    }

    private function parsePositiveInt(int|string $amount): ?int
    {
        if ( is_int($amount) )
        {
            return $amount >= 0 ? $amount : null;
        }

        $s = trim((string) $amount);

        if ( $s === '' ) return null;

        if ( !ctype_digit($s) ) return null;

        return (int) $s;
    }

    private function parsePositiveBytes(int|string $amount): ?int
    {
        try
        {
            $bytes = is_int($amount) ? $amount : Storage::toBytes((string) $amount);
        }
        catch (\Throwable)
        {
            return null;
        }

        return $bytes >= 0 ? $bytes : null;
    }

    private function isZeroAmount(int|string $amount): bool
    {
        if ( is_int($amount) ) return $amount === 0;

        $s = trim((string) $amount);

        return $s === '0' || $s === '0B' || $s === '0KB' || $s === '0MB' || $s === '0GB';
    }

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
        if ( $this->isZeroAmount($amount) )
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

            // BOOLEAN: not tracked by default
            if ( $feature->type === FeatureType::BOOLEAN )
            {
                return $this->usage($featureKey);
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
     * @param array<string, int|string> $map featureKey => amount
     * @return array<string, int>|false new usages by featureKey
     */
    public function refundMany(array $map, bool $strict = false): array|false
    {
        if ( empty($map)) return [];

        $map = array_filter($map, fn ($v) => !$this->isZeroAmount($v));

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

                if ( $feature->type === FeatureType::BOOLEAN ) continue;

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

    public function refundManyOrFail(array $map): array
    {
        $res = $this->refundMany($map, strict: true);

        return (array) $res;
    }

    /**
     * remainingQuotaMany:
     * Returns a map of featureKey => remainingQuota(featureKey)
     *
     * Example:
     *  [
     *    'sites' => 3,
     *    'storage' => '512MB',
     *    'custom_code' => 1,
     *    'unknown' => null,
     *  ]
     */
    public function remainingQuotaMany(array $features): array
    {
        $out = [];

        foreach ($features as $key => $value)
        {
            // allow passing ['sites', 'storage'] OR ['sites' => 1, 'storage' => '500MB']
            $featureKey = is_int($key) ? (string) $value : (string) $key;

            $out[$featureKey] = $this->remainingQuota($featureKey);
        }

        return $out;
    }

    /**
     * canConsumeMany:
     * Checks multiple features without writing anything.
     *
     * Input format:
     *  [
     *    'sites' => 1,
     *    'storage' => '500MB',
     *    'custom_code' => 1,
     *  ]
     *
     * Returns:
     *  - true if ALL can be consumed
     *  - false if any fails (non-strict)
     *
     * Strict:
     *  - throws QuotaExceededException on first failing item
    */
    public function canConsumeMany(array $features, bool $strict = false): bool
    {
        // Normalize to map: featureKey => aggregated amount
        $map = [];

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
            $raw = $this->planFeatureReader()->raw($featureKey);

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
                $n = is_int($amount) ? $amount : (ctype_digit(trim((string)$amount)) ? (int) trim((string)$amount) : null);

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
                try
                {
                    $bytes = is_int($amount) ? $amount : Storage::toBytes((string) $amount);
                }
                catch (\Throwable)
                {
                    $bytes = null;
                }

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
}
