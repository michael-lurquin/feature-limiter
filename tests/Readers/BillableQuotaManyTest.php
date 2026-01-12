<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Exceptions\QuotaExceededException;
use MichaelLurquin\FeatureLimiter\Tests\Concerns\InteractsWithFeatureLimiter;

class BillableQuotaManyTest extends TestCase
{
    use InteractsWithFeatureLimiter;

    public function test_remaining_quota_many_returns_map_for_each_key(): void
    {
        $reader = $this->starterReader([
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
            'storage' => ['type' => FeatureType::STORAGE, 'value' => '1GB'],
            'custom_code' => ['type' => FeatureType::BOOLEAN, 'enabled' => true],
        ]);

        // initial usage
        $reader->setUsage('sites', 1);
        $reader->setUsage('storage', 500 * 1024 * 1024); // 500MB

        $res = $reader->remainingQuotaMany(['sites', 'storage', 'custom_code', 'unknown']);

        $this->assertIsArray($res);
        $this->assertSame(2, $res['sites']); // 3 - 1
        $this->assertIsString($res['storage']); // depends on normalization, but must not be null
        $this->assertSame(1, $res['custom_code']); // enabled => 1
        $this->assertNull($res['unknown']);
    }

    public function test_can_consume_many_true_when_all_ok(): void
    {
        $reader = $this->starterReader([
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
            'storage' => ['type' => FeatureType::STORAGE, 'value' => '1GB'],
        ]);

        $reader->setUsage('sites', 1); // remaining 2

        $this->assertTrue($reader->canConsumeMany([
            'sites' => 2,
            'storage' => '500MB',
        ]));
    }

    public function test_can_consume_many_false_when_any_fails_non_strict(): void
    {
        $reader = $this->starterReader([
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
            'storage' => ['type' => FeatureType::STORAGE, 'value' => '1GB'],
        ]);

        $reader->setUsage('sites', 2); // remaining 1

        $this->assertFalse($reader->canConsumeMany([
            'sites' => 2, // fails
            'storage' => '100MB', // would be ok, but overall false
        ], strict: false));

        // Ensure no writes happened (this is a pure check)
        $this->assertSame(2, $reader->usage('sites'));
    }

    public function test_can_consume_many_strict_throws_quota_exceeded_exception(): void
    {
        $this->expectException(QuotaExceededException::class);

        $reader = $this->starterReader([
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
        ]);

        $reader->setUsage('sites', 3); // remaining 0

        $reader->canConsumeMany([
            'sites' => 1,
        ], strict: true);
    }

    public function test_can_consume_many_can_accept_list_syntax_defaults_amount_to_one(): void
    {
        $reader = $this->starterReader([
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
        ]);

        $reader->setUsage('sites', 2); // remaining 1

        $this->assertTrue($reader->canConsumeMany(['sites'])); // amount=1
        $this->assertFalse($reader->canConsumeMany(['sites', 'sites'])); // checks twice (2x1) but still check-only; will return false on 2nd item depending on current remaining
    }
}
