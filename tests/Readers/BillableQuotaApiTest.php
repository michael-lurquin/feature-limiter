<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Readers;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Facades\FeatureLimiter;
use MichaelLurquin\FeatureLimiter\Tests\Fakes\FakeBillingProvider;

class BillableQuotaApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeBillingProvider::$resolver = null;
    }

    public function test_remaining_quota_returns_null_if_feature_not_found(): void
    {
        Plan::create(['key' => 'starter', 'name' => 'Starter']);
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        FeatureLimiter::grant('starter')->feature('sites')->quota(3);

        $billable = new class { public int $id = 1; };

        FakeBillingProvider::$resolver = fn () => Plan::where('key', 'starter')->first();

        $reader = FeatureLimiter::for($billable);

        $this->assertNull($reader->remainingQuota('unknown'));
        $this->assertFalse($reader->canConsume('unknown', 1));
        $this->assertTrue($reader->exceededQuota('unknown', 1));
    }

    public function test_integer_quota_uses_plan_limit_minus_current_usage(): void
    {
        // Plan starter: sites = 3
        Plan::create(['key' => 'starter', 'name' => 'Starter']);
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        FeatureLimiter::grant('starter')->feature('sites')->quota(3);

        $billable = new class { public int $id = 1; };

        FakeBillingProvider::$resolver = fn () => Plan::where('key', 'starter')->first();

        $reader = FeatureLimiter::for($billable);

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
        Plan::create(['key' => 'starter', 'name' => 'Starter']);
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);

        FeatureLimiter::grant('starter')->feature('sites')->quota(3);

        $billable = new class { public int $id = 1; };
        FakeBillingProvider::$resolver = fn () => Plan::where('key', 'starter')->first();

        $reader = FeatureLimiter::for($billable);

        $reader->setUsage('sites', 3);

        $this->assertSame(0, $reader->remainingQuota('sites'));
        $this->assertTrue($reader->canConsume('sites', 0));
        $this->assertFalse($reader->canConsume('sites', 1));
        $this->assertTrue($reader->exceededQuota('sites', 1));
    }

    public function test_unlimited_quota_always_allows_and_remaining_is_unlimited(): void
    {
        Plan::create(['key' => 'pro', 'name' => 'Pro']);
        Feature::create(['key' => 'storage', 'name' => 'Storage', 'type' => FeatureType::STORAGE]);

        FeatureLimiter::grant('pro')->feature('storage')->unlimited();

        $billable = new class { public int $id = 1; };
        FakeBillingProvider::$resolver = fn () => Plan::where('key', 'pro')->first();

        $reader = FeatureLimiter::for($billable);

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
        Plan::create(['key' => 'starter', 'name' => 'Starter']);
        Feature::create(['key' => 'custom_code', 'name' => 'Custom code', 'type' => FeatureType::BOOLEAN]);

        FeatureLimiter::grant('starter')->feature('custom_code')->disabled();

        $billable = new class { public int $id = 1; };
        FakeBillingProvider::$resolver = fn () => Plan::where('key', 'starter')->first();

        $reader = FeatureLimiter::for($billable);

        $this->assertSame(0, $reader->remainingQuota('custom_code')); // disabled
        $this->assertFalse($reader->canConsume('custom_code', 1));
        $this->assertTrue($reader->exceededQuota('custom_code', 1));

        FeatureLimiter::grant('starter')->feature('custom_code')->enabled();

        $this->assertSame(1, $reader->remainingQuota('custom_code')); // enabled
        $this->assertTrue($reader->canConsume('custom_code', 1));
        $this->assertFalse($reader->exceededQuota('custom_code', 1));
    }

    public function test_storage_quota_compares_bytes_of_remaining_against_amount_string(): void
    {
        // Plan starter: storage = 1GB
        Plan::create(['key' => 'starter', 'name' => 'Starter']);
        Feature::create(['key' => 'storage', 'name' => 'Storage', 'type' => FeatureType::STORAGE]);

        FeatureLimiter::grant('starter')->feature('storage')->value('1GB');

        $billable = new class { public int $id = 1; };
        FakeBillingProvider::$resolver = fn () => Plan::where('key', 'starter')->first();

        $reader = FeatureLimiter::for($billable);

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
        Plan::create(['key' => 'starter', 'name' => 'Starter']);
        Feature::create(['key' => 'sites', 'name' => 'Sites', 'type' => FeatureType::INTEGER]);
        Feature::create(['key' => 'storage', 'name' => 'Storage', 'type' => FeatureType::STORAGE]);

        FeatureLimiter::grant('starter')->feature('sites')->quota(3);
        FeatureLimiter::grant('starter')->feature('storage')->value('1GB');

        $billable = new class { public int $id = 1; };
        FakeBillingProvider::$resolver = fn () => Plan::where('key', 'starter')->first();

        $reader = FeatureLimiter::for($billable);

        // integer amount négatif => false
        $this->assertFalse($reader->canConsume('sites', -1));
        $this->assertTrue($reader->exceededQuota('sites', -1));

        // storage amount invalide => false
        $this->assertFalse($reader->canConsume('storage', 'abc'));
        $this->assertTrue($reader->exceededQuota('storage', 'abc'));
    }
}