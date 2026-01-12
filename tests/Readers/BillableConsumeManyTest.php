<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Exceptions\QuotaExceededException;
use MichaelLurquin\FeatureLimiter\Tests\Concerns\InteractsWithFeatureLimiter;

class BillableConsumeManyTest extends TestCase
{
    use InteractsWithFeatureLimiter;

    private function starterReader(array $overrides = [])
    {
        $features = array_replace_recursive([
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
            'storage' => ['type' => FeatureType::STORAGE, 'value' => '1GB'],
            'custom_code' => ['type' => FeatureType::BOOLEAN, 'enabled' => true],
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

    private function proReaderWithStorageUnlimited()
    {
        $this->flPlan('pro', 'Pro');
        $this->flFeature('storage', FeatureType::STORAGE);
        $this->flGrantValue('pro', 'storage', 'unlimited');

        $billable = $this->flBillable(1);
        $this->flResolvePlan('pro');

        return $this->flReader($billable);
    }

    public function test_it_consumes_many_atomically_success(): void
    {
        $reader = $this->starterReader();

        $out = $reader->consumeMany([
            'sites' => 2,
            'storage' => '500MB',
        ]);

        $this->assertIsArray($out);
        $this->assertSame(2, $reader->usage('sites'));
        $this->assertSame(500 * 1024 * 1024, $reader->usage('storage'));
    }

    public function test_it_is_all_or_nothing_when_one_feature_exceeds_non_strict(): void
    {
        $reader = $this->starterReader();

        // First: consume 2 sites
        $reader->consumeUsage('sites', 2, strict: true);
        $this->assertSame(2, $reader->usage('sites'));

        // Now attempt: sites +2 (would exceed: remaining 1) + storage 100MB
        $res = $reader->consumeMany([
            'sites' => 2,
            'storage' => '100MB',
        ], strict: false);

        $this->assertFalse($res);

        // Nothing should have changed
        $this->assertSame(2, $reader->usage('sites'));
        $this->assertSame(0, $reader->usage('storage'));
    }

    public function test_it_is_all_or_nothing_when_one_feature_exceeds_strict(): void
    {
        $reader = $this->starterReader();

        $reader->consumeUsage('sites', 3, strict: true);
        $this->assertSame(3, $reader->usage('sites'));

        $this->expectException(QuotaExceededException::class);

        try {
            $reader->consumeMany([
                'sites' => 1,
                'storage' => '100MB',
            ], strict: true);
        } finally {
            // still unchanged (all-or-nothing)
            $this->assertSame(3, $reader->usage('sites'));
            $this->assertSame(0, $reader->usage('storage'));
        }
    }

    public function test_unlimited_feature_always_allows_and_tracks_usage(): void
    {
        $reader = $this->proReaderWithStorageUnlimited();

        $res = $reader->consumeMany([
            'storage' => '2GB',
        ], strict: true);

        $this->assertIsArray($res);
        $this->assertSame(2 * 1024 * 1024 * 1024, $reader->usage('storage'));
    }

    public function test_boolean_in_consume_many_checks_enabled_but_does_not_track_usage(): void
    {
        $reader = $this->starterReader();

        $res = $reader->consumeMany([
            'custom_code' => 1,
            'sites' => 1,
        ], strict: true);

        $this->assertIsArray($res);
        $this->assertSame(1, $reader->usage('sites'));
        $this->assertSame(0, $reader->usage('custom_code')); // by default booleans not tracked
    }

    public function test_invalid_amount_fails_consistently(): void
    {
        $reader = $this->starterReader();

        $res = $reader->consumeMany([
            'sites' => 'nope',
            'storage' => '100MB',
        ], strict: false);

        $this->assertFalse($res);
        $this->assertSame(0, $reader->usage('sites'));
        $this->assertSame(0, $reader->usage('storage'));
    }
}
