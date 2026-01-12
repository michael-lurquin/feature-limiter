<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Exceptions\QuotaExceededException;
use MichaelLurquin\FeatureLimiter\Tests\Concerns\InteractsWithFeatureLimiter;

class BillableConsumeUsageTest extends TestCase
{
    use InteractsWithFeatureLimiter;

    private function starterReader(array $overrides = [])
    {
        $features = array_replace_recursive([
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
            'storage' => ['type' => FeatureType::STORAGE, 'value' => '1GB'],
        ], $overrides);

        $this->flPlan('starter');

        foreach ($features as $key => $def)
        {
            $type = $def['type'] ?? null;

            if ( !$type instanceof FeatureType )
            {
                throw new \InvalidArgumentException("Invalid feature definition for {$key}");
            }

            $this->flFeature($key, $type);

            if ( $type === FeatureType::INTEGER && array_key_exists('quota', $def) )
            {
                $this->flGrantQuota('starter', $key, (int) $def['quota']);
            }
            elseif ( $type === FeatureType::BOOLEAN && array_key_exists('enabled', $def) )
            {
                $this->flGrantEnabled('starter', $key, (bool) $def['enabled']);
            }
            elseif ( array_key_exists('value', $def) )
            {
                $this->flGrantValue('starter', $key, $def['value']);
            }
        }

        $billable = $this->flBillable(1);

        $this->flResolvePlan('starter');

        return $this->flReader($billable);
    }

    public function test_it_consumes_integer_usage_within_quota(): void
    {
        $reader = $this->starterReader();

        $this->assertSame(0, $reader->usage('sites'));

        // consume 2 => ok
        $this->assertSame(2, $reader->consumeUsage('sites', 2));
        $this->assertSame(2, $reader->usage('sites'));

        // remaining 1 => consume 1 => ok
        $this->assertSame(3, $reader->consumeUsage('sites', 1));
        $this->assertSame(3, $reader->usage('sites'));
    }

    public function test_integer_consume_returns_false_if_exceeds_quota_in_non_strict_mode(): void
    {
        $reader = $this->starterReader();

        $this->assertSame(0, $reader->usage('sites'));

        // consume 3 => ok
        $this->assertSame(3, $reader->consumeUsage('sites', 3));
        $this->assertSame(3, $reader->usage('sites'));

        // try consume 1 more => false + usage unchanged
        $this->assertFalse($reader->consumeUsage('sites', 1, strict: false));
        $this->assertSame(3, $reader->usage('sites'));
    }

    public function test_integer_consume_throws_if_exceeds_quota_in_strict_mode(): void
    {
        $this->expectException(QuotaExceededException::class);

        $reader = $this->starterReader();

        // consume 4 > 3 => throws
        $reader->consumeUsage('sites', 4, strict: true);
    }

    public function test_it_consumes_storage_usage_as_bytes_within_quota(): void
    {
        $reader = $this->starterReader();

        $this->assertSame(0, $reader->usage('storage'));

        // consume 500MB (=> bytes)
        $newUsed = $reader->consumeUsage('storage', '500MB');
        $this->assertSame(500 * 1024 * 1024, $newUsed);
        $this->assertSame(500 * 1024 * 1024, $reader->usage('storage'));

        // consume 600MB more => should fail (remaining 524288000 bytes ~ 500MB)
        $this->assertFalse($reader->consumeUsage('storage', '600MB', strict: false));
        $this->assertSame(500 * 1024 * 1024, $reader->usage('storage'));

        // consume 10MB => ok
        $newUsed = $reader->consumeUsage('storage', '10MB');
        $this->assertSame((500 + 10) * 1024 * 1024, $newUsed);
    }

    public function test_storage_consume_throws_when_invalid_amount_in_strict_mode(): void
    {
        $this->expectException(QuotaExceededException::class);

        $reader = $this->starterReader();

        // invalid storage string
        $reader->consumeUsage('storage', 'banana', strict: true);
    }
}
