<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Tests\Concerns\InteractsWithFeatureLimiter;

class BillableQuotaApiTest extends TestCase
{
    use InteractsWithFeatureLimiter;

    private function readerForPlan(string $planKey, array $features)
    {
        $this->flPlan($planKey, ucfirst($planKey));

        foreach ($features as $key => $def)
        {
            $type = $def['type'] ?? null;

            if ( !$type instanceof FeatureType )
            {
                throw new \InvalidArgumentException("Invalid feature definition for {$key}");
            }

            $this->flFeature($key, $type);

            if ( array_key_exists('unlimited', $def) && $def['unlimited'] === true )
            {
                $this->flGrantValue($planKey, $key, 'unlimited');
            }
            elseif ( $type === FeatureType::INTEGER && array_key_exists('quota', $def) )
            {
                $this->flGrantQuota($planKey, $key, (int) $def['quota']);
            }
            elseif ( $type === FeatureType::BOOLEAN && array_key_exists('enabled', $def) )
            {
                $this->flGrantEnabled($planKey, $key, (bool) $def['enabled']);
            }
            elseif ( array_key_exists('value', $def) )
            {
                $this->flGrantValue($planKey, $key, $def['value']);
            }
        }

        $billable = $this->flBillable(1);
        $this->flResolvePlan($planKey);

        return $this->flReader($billable);
    }

    public function test_remaining_quota_returns_null_if_feature_not_found(): void
    {
        $reader = $this->readerForPlan('starter', [
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
        ]);

        $this->assertNull($reader->remainingQuota('unknown'));
        $this->assertFalse($reader->canConsume('unknown', 1));
        $this->assertTrue($reader->exceededQuota('unknown', 1));
    }

    public function test_integer_quota_uses_plan_limit_minus_current_usage(): void
    {
        // Plan starter: sites = 3
        $reader = $this->readerForPlan('starter', [
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
        ]);

        // usage initial 0 => remaining 3
        $this->assertSame(0, $reader->usage('sites'));
        $this->assertSame(3, $reader->remainingQuota('sites'));
        $this->assertTrue($reader->canConsume('sites', 1));
        $this->assertFalse($reader->exceededQuota('sites', 1));

        // consume 2 => usage 2 => remaining 1
        $reader->incrementUsage('sites', 2);

        $this->assertSame(2, $reader->usage('sites'));
        $this->assertSame(1, $reader->remainingQuota('sites'));
        $this->assertTrue($reader->canConsume('sites', 1));
        $this->assertFalse($reader->canConsume('sites', 2));
        $this->assertTrue($reader->exceededQuota('sites', 2));
    }

    public function test_integer_quota_at_limit_cannot_consume_more(): void
    {
        $reader = $this->readerForPlan('starter', [
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
        ]);

        $reader->setUsage('sites', 3);

        $this->assertSame(0, $reader->remainingQuota('sites'));
        $this->assertTrue($reader->canConsume('sites', 0));
        $this->assertFalse($reader->canConsume('sites', 1));
        $this->assertTrue($reader->exceededQuota('sites', 1));
    }

    public function test_unlimited_quota_always_allows_and_remaining_is_unlimited(): void
    {
        $reader = $this->readerForPlan('pro', [
            'storage' => ['type' => FeatureType::STORAGE, 'unlimited' => true],
        ]);

        $this->assertSame('unlimited', $reader->remainingQuota('storage'));
        $this->assertTrue($reader->canConsume('storage', '500MB'));
        $this->assertFalse($reader->exceededQuota('storage', '500MB'));

        // même si usage existant, unlimited => toujours OK
        $reader->setUsage('storage', 999999);
        $this->assertSame('unlimited', $reader->remainingQuota('storage'));
        $this->assertTrue($reader->canConsume('storage', '999TB'));
    }

    public function test_boolean_quota_is_enabled_or_disabled_only(): void
    {
        $reader = $this->readerForPlan('starter', [
            'custom_code' => ['type' => FeatureType::BOOLEAN, 'enabled' => false],
        ]);

        $this->assertSame(0, $reader->remainingQuota('custom_code')); // disabled
        $this->assertFalse($reader->canConsume('custom_code', 1));
        $this->assertTrue($reader->exceededQuota('custom_code', 1));

        $this->flGrantEnabled('starter', 'custom_code', true);

        $this->assertSame(1, $reader->remainingQuota('custom_code')); // enabled
        $this->assertTrue($reader->canConsume('custom_code', 1));
        $this->assertFalse($reader->exceededQuota('custom_code', 1));
    }

    public function test_storage_quota_compares_bytes_of_remaining_against_amount_string(): void
    {
        // Plan starter: storage = 1GB
        $reader = $this->readerForPlan('starter', [
            'storage' => ['type' => FeatureType::STORAGE, 'value' => '1GB'],
        ]);

        // usage 0 => remaining "1GB"
        $this->assertSame('1GB', $reader->remainingQuota('storage'));
        $this->assertTrue($reader->canConsume('storage', '500MB'));
        $this->assertFalse($reader->canConsume('storage', '2GB'));

        // simulate already used 500MB (stocké en bytes par ton usage repo)
        $reader->setUsage('storage', 500 * 1024 * 1024);

        // remaining devrait être (1GB - 500MB) -> "512MB" ou "0.5GB" selon ta normalisation
        // => ici on vérifie surtout le comportement canConsume
        $this->assertTrue($reader->canConsume('storage', '100MB'));
        $this->assertFalse($reader->canConsume('storage', '900MB'));
    }

    public function test_can_consume_rejects_invalid_amounts(): void
    {
        $reader = $this->readerForPlan('starter', [
            'sites' => ['type' => FeatureType::INTEGER, 'quota' => 3],
            'storage' => ['type' => FeatureType::STORAGE, 'value' => '1GB'],
        ]);

        // integer amount négatif => false
        $this->assertFalse($reader->canConsume('sites', -1));
        $this->assertTrue($reader->exceededQuota('sites', -1));

        // storage amount invalide => false
        $this->assertFalse($reader->canConsume('storage', 'abc'));
        $this->assertTrue($reader->exceededQuota('storage', 'abc'));
    }
}
