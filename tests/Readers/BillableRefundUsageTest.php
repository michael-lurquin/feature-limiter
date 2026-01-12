<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Exceptions\QuotaExceededException;
use MichaelLurquin\FeatureLimiter\Tests\Concerns\InteractsWithFeatureLimiter;

class BillableRefundUsageTest extends TestCase
{
    use InteractsWithFeatureLimiter;

    private function starterReader(array $overrides = [])
    {
        $features = array_replace_recursive([
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 10],
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

    public function test_refund_integer_decrements_usage(): void
    {
        $reader = $this->starterReader();

        $reader->setUsage('sites', 5);

        $this->assertSame(3, $reader->refundUsage('sites', 2));
        $this->assertSame(3, $reader->usage('sites'));
    }

    public function test_refund_invalid_amount_strict_throws(): void
    {
        $this->expectException(QuotaExceededException::class);

        $reader = $this->starterReader();

        $reader->refundUsage('sites', 'abc', strict: true);
    }

    public function test_refund_integer_clamps_to_zero(): void
    {
        $reader = $this->starterReader();

        $reader->setUsage('sites', 3);

        $this->assertSame(0, $reader->refundUsage('sites', 10));
        $this->assertSame(0, $reader->usage('sites'));
    }

    public function test_refund_storage_amount_string_is_converted_to_bytes(): void
    {
        $reader = $this->starterReader();

        // usage en bytes
        $reader->setUsage('storage', 700 * 1024 * 1024); // 700MB
        $this->assertSame(700 * 1024 * 1024, $reader->usage('storage'));

        // refund "200MB" => 500MB
        $this->assertSame(500 * 1024 * 1024, $reader->refundUsage('storage', '200MB'));
        $this->assertSame(500 * 1024 * 1024, $reader->usage('storage'));
    }

    public function test_refund_invalid_amount_non_strict_returns_false_and_does_not_change_usage(): void
    {
        $reader = $this->starterReader();

        $reader->setUsage('sites', 5);

        $this->assertFalse($reader->refundUsage('sites', 'abc', strict: false));
        $this->assertSame(5, $reader->usage('sites'));
    }

    public function test_refund_invalid_amount_strict_throws_quota_exceeded_exception(): void
    {
        $this->expectException(QuotaExceededException::class);

        $reader = $this->starterReader();

        $reader->setUsage('sites', 5);

        $reader->refundUsage('sites', 'abc', strict: true);
    }

    public function test_refund_feature_not_in_plan_non_strict_returns_false(): void
    {
        $this->flPlan('starter');

        // Feature exists in DB but is NOT attached to the plan
        $this->flFeature('sites', FeatureType::INTEGER);

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');

        $reader = $this->flReader($billable);

        $this->assertFalse($reader->refundUsage('sites', 1, strict: false));
    }

    public function test_refund_many_updates_multiple_features_atomically(): void
    {
        $reader = $this->starterReader();

        $reader->setUsage('sites', 5);
        $reader->setUsage('storage', 600 * 1024 * 1024); // 600MB

        $result = $reader->refundMany([
            'sites' => 2,
            'storage' => '100MB',
        ]);

        $this->assertIsArray($result);
        $this->assertSame(3, $result['sites']);
        $this->assertSame(500 * 1024 * 1024, $result['storage']);

        $this->assertSame(3, $reader->usage('sites'));
        $this->assertSame(500 * 1024 * 1024, $reader->usage('storage'));
    }

    public function test_refund_many_with_missing_feature_non_strict_returns_false_and_makes_no_changes(): void
    {
        $reader = $this->starterReader();

        $reader->setUsage('sites', 5);
        $reader->setUsage('storage', 600 * 1024 * 1024);

        $res = $reader->refundMany([
            'sites' => 2,
            'unknown' => 1,
        ], strict: false);

        $this->assertFalse($res);

        // nothing changed
        $this->assertSame(5, $reader->usage('sites'));
        $this->assertSame(600 * 1024 * 1024, $reader->usage('storage'));
    }

    public function test_refund_many_strict_throws_on_missing_feature(): void
    {
        $this->expectException(QuotaExceededException::class);

        $reader = $this->starterReader();

        $reader->refundMany([
            'sites' => 1,
            'unknown' => 1,
        ], strict: true);
    }

    public function test_refund_many_invalid_amount_non_strict_returns_false_and_makes_no_changes(): void
    {
        $reader = $this->starterReader();

        $reader->setUsage('sites', 5);
        $reader->setUsage('storage', 600 * 1024 * 1024);

        $res = $reader->refundMany([
            'sites' => 2,
            'storage' => 'nope',
        ], strict: false);

        $this->assertFalse($res);

        // nothing changed
        $this->assertSame(5, $reader->usage('sites'));
        $this->assertSame(600 * 1024 * 1024, $reader->usage('storage'));
    }

    public function test_refund_many_invalid_amount_strict_throws(): void
    {
        $this->expectException(QuotaExceededException::class);

        $reader = $this->starterReader();

        $reader->refundMany([
            'sites' => 2,
            'storage' => 'nope',
        ], strict: true);
    }
}
