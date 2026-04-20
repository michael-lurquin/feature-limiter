<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Exceptions\QuotaExceededException;
use MichaelLurquin\FeatureLimiter\Tests\Concerns\InteractsWithFeatureLimiter;

class EdgeCaseTest extends TestCase
{
    use InteractsWithFeatureLimiter;

    /**
     * Test that consuming a BOOLEAN feature throws an exception (not usage-tracked).
     */
    public function test_consume_boolean_feature_should_be_rejected(): void
    {
        $this->flPlan('starter');
        $this->flFeature('custom_code', FeatureType::BOOLEAN);
        $this->flGrantEnabled('starter', 'custom_code', true);

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        // Attempting to consume a BOOLEAN feature should throw or return false
        // (Currently returns current usage; should throw or reject)
        $result = $reader->consume('custom_code', 1, strict: false);
        $this->assertFalse($result);
    }

    /**
     * Test that refunding a BOOLEAN feature throws an exception (not usage-tracked).
     */
    public function test_refund_boolean_feature_should_be_rejected(): void
    {
        $this->flPlan('starter');
        $this->flFeature('custom_code', FeatureType::BOOLEAN);
        $this->flGrantEnabled('starter', 'custom_code', true);

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        // Attempting to refund a BOOLEAN feature should throw or return false
        $result = $reader->refund('custom_code', 1, strict: false);
        $this->assertFalse($result);
    }

    /**
     * Test that consuming zero amount is a no-op.
     */
    public function test_consume_zero_amount_is_noop(): void
    {
        $this->flPlan('starter');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flGrantQuota('starter', 'sites', 10);

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        $initialUsage = $reader->usage('sites');

        // Consume 0 should return current usage without modification
        $result = $reader->consume('sites', 0);
        $this->assertSame($initialUsage, $result);
        $this->assertSame($initialUsage, $reader->usage('sites'));
    }

    /**
     * Test that consuming zero storage is a no-op.
     */
    public function test_consume_zero_storage_is_noop(): void
    {
        $this->flPlan('starter');
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantValue('starter', 'storage', '1GB');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        $initialUsage = $reader->usage('storage');

        $result = $reader->consume('storage', '0B');
        $this->assertSame($initialUsage, $result);
        $this->assertSame($initialUsage, $reader->usage('storage'));
    }

    /**
     * Test that consumeMany with all zero amounts returns empty array.
     */
    public function test_consumeMany_all_zeros_returns_empty(): void
    {
        $this->flPlan('starter');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantQuota('starter', 'sites', 10);
        $this->flGrantValue('starter', 'storage', '1GB');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        $result = $reader->consumeMany([
            'sites' => 0,
            'storage' => '0B',
        ]);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test unlimited feature always allows consumption (no quota check).
     */
    public function test_unlimited_integer_feature_always_allows_consumption(): void
    {
        $this->flPlan('pro');
        $this->flFeature('api_calls', FeatureType::INTEGER);
        $this->flGrantValue('pro', 'api_calls', 'unlimited');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('pro');
        $reader = $this->flReader($billable);

        // Consume huge amount
        $result = $reader->consume('api_calls', 1_000_000_000);
        $this->assertSame(1_000_000_000, $result);

        // Consume more
        $result = $reader->consume('api_calls', 999_999_999);
        $this->assertSame(1_999_999_999, $result);

        // Still returns true for canConsume
        $this->assertTrue($reader->canConsume('api_calls', PHP_INT_MAX));
    }

    /**
     * Test unlimited storage feature always allows consumption.
     */
    public function test_unlimited_storage_feature_always_allows_consumption(): void
    {
        $this->flPlan('pro');
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantValue('pro', 'storage', 'unlimited');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('pro');
        $reader = $this->flReader($billable);

        $result = $reader->consume('storage', '500GB');
        $this->assertSame(500 * 1024 * 1024 * 1024, $result);

        // Can still consume more
        $this->assertTrue($reader->canConsume('storage', '999GB'));
    }

    /**
     * Test integer overflow boundary (near PHP_INT_MAX).
     */
    public function test_integer_consumption_near_php_int_max(): void
    {
        $this->flPlan('pro');
        $this->flFeature('api_calls', FeatureType::INTEGER);
        // Set quota to near PHP_INT_MAX
        $this->flGrantValue('pro', 'api_calls', PHP_INT_MAX - 100);

        $billable = $this->flBillable(1);
        $this->flResolvePlan('pro');
        $reader = $this->flReader($billable);

        // Consume up to quota - 1
        $result = $reader->consume('api_calls', PHP_INT_MAX - 101);
        $this->assertSame(PHP_INT_MAX - 101, $result);

        // Next consumption should fail (would exceed quota)
        $canConsume = $reader->canConsume('api_calls', 2);
        $this->assertFalse($canConsume);

        // But consuming 1 should work
        $result = $reader->consume('api_calls', 1);
        $this->assertSame(PHP_INT_MAX - 100, $result);
    }

    /**
     * Test refund clamps to zero (never negative).
     */
    public function test_refund_clamps_usage_to_zero(): void
    {
        $this->flPlan('starter');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flGrantQuota('starter', 'sites', 10);

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        // Set usage to 5
        $reader->setUsage('sites', 5);
        $this->assertSame(5, $reader->usage('sites'));

        // Refund more than current usage
        $result = $reader->refund('sites', 10);
        $this->assertSame(0, $result); // Clamped to 0
    }

    /**
     * Test storage precision in byte conversion (roundtrip).
     */
    public function test_storage_precision_roundtrip(): void
    {
        $this->flPlan('pro');
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantValue('pro', 'storage', '1.5GB');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('pro');
        $reader = $this->flReader($billable);

        // Consume 0.75 GB
        $result = $reader->consume('storage', '0.75GB');
        $expectedBytes = 805306368; // 0.75GB in bytes
        $this->assertSame($expectedBytes, $result);

        // Remaining should be approximately 0.75GB (displayed as 768MB in binary units)
        $remaining = $reader->remainingQuota('storage');
        $this->assertStringContainsString('MB', $remaining);
        // Note: 0.75GB = 768MB (768 < 1024, so fromBytes stops at MB unit)
    }

    /**
     * Test consumeMany is truly atomic (all-or-nothing).
     */
    public function test_consumeMany_atomic_rollback_on_single_failure(): void
    {
        $this->flPlan('starter');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantQuota('starter', 'sites', 3);
        $this->flGrantValue('starter', 'storage', '1GB');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        // Consume 2 sites first
        $reader->consume('sites', 2);
        $this->assertSame(2, $reader->usage('sites'));

        // Try to consume: 2 sites (would exceed) + 500MB storage (OK)
        // Should fail entirely, no changes
        $result = $reader->consumeMany([
            'sites' => 2,
            'storage' => '500MB',
        ], strict: false);

        $this->assertFalse($result);

        // Verify nothing changed
        $this->assertSame(2, $reader->usage('sites'));
        $this->assertSame(0, $reader->usage('storage'));
    }

    /**
     * Test refundMany is truly atomic.
     */
    public function test_refundMany_atomic_rollback_on_single_failure(): void
    {
        $this->flPlan('starter');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantQuota('starter', 'sites', 10);
        $this->flGrantValue('starter', 'storage', '1GB');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        // Set usage
        $reader->consume('sites', 5);
        $reader->consume('storage', '500MB');

        // Try to refund: invalid amount for storage
        $result = $reader->refundMany([
            'sites' => 2,
            'storage' => 'invalid',
        ], strict: false);

        $this->assertFalse($result);

        // Verify nothing changed
        $this->assertSame(5, $reader->usage('sites'));
        $this->assertSame(500 * 1024 * 1024, $reader->usage('storage'));
    }

    /**
     * Test consuming feature not assigned to plan fails gracefully.
     */
    public function test_consume_missing_feature_returns_false(): void
    {
        $this->flPlan('starter');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flGrantQuota('starter', 'sites', 3);

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        // Try to consume feature not assigned to plan
        $result = $reader->consume('unknown_feature', 1, strict: false);
        $this->assertFalse($result);
    }

    /**
     * Test consuming feature not assigned to plan throws in strict mode.
     */
    public function test_consume_missing_feature_throws_strict(): void
    {
        $this->expectException(QuotaExceededException::class);

        $this->flPlan('starter');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flGrantQuota('starter', 'sites', 3);

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        $reader->consume('unknown_feature', 1, strict: true);
    }

    /**
     * Test consuming with invalid storage format.
     */
    public function test_consume_invalid_storage_format_non_strict(): void
    {
        $this->flPlan('starter');
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantValue('starter', 'storage', '1GB');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        $result = $reader->consume('storage', 'banana', strict: false);
        $this->assertFalse($result);
    }

    /**
     * Test consuming with invalid storage format throws in strict mode.
     */
    public function test_consume_invalid_storage_format_strict(): void
    {
        $this->expectException(QuotaExceededException::class);

        $this->flPlan('starter');
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantValue('starter', 'storage', '1GB');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        $reader->consume('storage', 'not_valid_format', strict: true);
    }

    /**
     * Test remainingQuota when usage equals quota.
     */
    public function test_remaining_quota_at_limit(): void
    {
        $this->flPlan('starter');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flGrantQuota('starter', 'sites', 3);

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        // Consume all quota
        $reader->consume('sites', 3);

        // Remaining should be 0
        $remaining = $reader->remainingQuota('sites');
        $this->assertSame(0, $remaining);

        // canConsume(0) should be true
        $this->assertTrue($reader->canConsume('sites', 0));

        // canConsume(1) should be false
        $this->assertFalse($reader->canConsume('sites', 1));
    }

    /**
     * Test canConsumeMany with one feature failing.
     */
    public function test_canConsumeMany_returns_false_if_any_fails(): void
    {
        $this->flPlan('starter');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantQuota('starter', 'sites', 3);
        $this->flGrantValue('starter', 'storage', '1GB');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('starter');
        $reader = $this->flReader($billable);

        // Consume some quota
        $reader->consume('sites', 3);

        // Check if can consume both (sites would fail, storage ok)
        $canConsume = $reader->canConsumeMany([
            'sites' => 1,
            'storage' => '500MB',
        ]);

        $this->assertFalse($canConsume);
    }

    /**
     * Test remainingQuotaMany with mix of unlimited and limited.
     */
    public function test_remainingQuotaMany_with_unlimited(): void
    {
        $this->flPlan('pro');
        $this->flFeature('sites', FeatureType::INTEGER);
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantQuota('pro', 'sites', 100);
        $this->flGrantValue('pro', 'storage', 'unlimited');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('pro');
        $reader = $this->flReader($billable);

        $remaining = $reader->remainingQuotaMany(['sites', 'storage']);

        $this->assertSame(100, $remaining['sites']);
        $this->assertSame('unlimited', $remaining['storage']);
    }
}
