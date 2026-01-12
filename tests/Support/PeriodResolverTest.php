<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Support;

use Carbon\CarbonImmutable;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Enums\ResetPeriod;
use MichaelLurquin\FeatureLimiter\Support\PeriodResolver;

class PeriodResolverTest extends TestCase
{
    public function test_it_resolves_periods_from_reset_values(): void
    {
        $now = CarbonImmutable::parse('2024-03-15 12:34:56');
        CarbonImmutable::setTestNow($now);

        try
        {
            $resolver = new PeriodResolver();

            $daily = new Feature();
            $daily->reset_period = ResetPeriod::DAILY;
            $this->assertSame(['2024-03-15', '2024-03-15'], $resolver->forFeature($daily));

            $monthly = new Feature();
            $monthly->reset_period = ResetPeriod::MONTHLY;
            $this->assertSame(['2024-03-01', '2024-03-31'], $resolver->forFeature($monthly));

            $yearly = new Feature();
            $yearly->reset_period = ResetPeriod::YEARLY;
            $this->assertSame(['2024-01-01', '2024-12-31'], $resolver->forFeature($yearly));

            $none = new Feature();
            $none->reset_period = ResetPeriod::NONE;
            $this->assertSame(['1970-01-01', '9999-12-31'], $resolver->forFeature($none));
        }
        finally
        {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_none_returns_lifetime_period(): void
    {
        $resolver = new PeriodResolver();

        $feature = Feature::create([
            'key' => 'sites',
            'name' => 'Sites',
            'type' => FeatureType::INTEGER,
            'reset_period' => ResetPeriod::NONE,
        ]);

        [$start, $end] = $resolver->forFeature($feature, CarbonImmutable::parse('2026-01-09'));

        $this->assertSame('1970-01-01', $start);
        $this->assertSame('9999-12-31', $end);
    }

    public function test_daily_returns_start_and_end_of_day(): void
    {
        $resolver = new PeriodResolver();

        $feature = Feature::create([
            'key' => 'sites',
            'name' => 'Sites',
            'type' => FeatureType::INTEGER,
            'reset_period' => ResetPeriod::DAILY,
        ]);

        [$start, $end] = $resolver->forFeature($feature, CarbonImmutable::parse('2026-01-09 15:30:00'));

        $this->assertSame('2026-01-09', $start);
        $this->assertSame('2026-01-09', $end);
    }

    public function test_daily_returns_start_and_end_of_week(): void
    {
        $resolver = new PeriodResolver();

        $feature = Feature::create([
            'key' => 'sites',
            'name' => 'Sites',
            'type' => FeatureType::INTEGER,
            'reset_period' => ResetPeriod::WEEKLY,
        ]);

        [$start, $end] = $resolver->forFeature($feature, CarbonImmutable::parse('2026-01-09'));

        $this->assertSame('2026-01-05', $start);
        $this->assertSame('2026-01-11', $end);
    }

    public function test_monthly_returns_start_and_end_of_month(): void
    {
        $resolver = new PeriodResolver();

        $feature = Feature::create([
            'key' => 'sites',
            'name' => 'Sites',
            'type' => FeatureType::INTEGER,
            'reset_period' => ResetPeriod::MONTHLY,
        ]);

        [$start, $end] = $resolver->forFeature($feature, CarbonImmutable::parse('2026-02-10'));

        $this->assertSame('2026-02-01', $start);
        $this->assertSame('2026-02-28', $end); // 2026 n'est pas bissextile
    }

    public function test_yearly_returns_start_and_end_of_year(): void
    {
        $resolver = new PeriodResolver();

        $feature = Feature::create([
            'key' => 'sites',
            'name' => 'Sites',
            'type' => FeatureType::INTEGER,
            'reset_period' => ResetPeriod::YEARLY,
        ]);

        [$start, $end] = $resolver->forFeature($feature, CarbonImmutable::parse('2026-07-12'));

        $this->assertSame('2026-01-01', $start);
        $this->assertSame('2026-12-31', $end);
    }

    public function test_invalid_period_falls_back_to_lifetime(): void
    {
        $resolver = new PeriodResolver();

        $feature = Feature::create([
            'key' => 'sites',
            'name' => 'Sites',
            'type' => FeatureType::INTEGER,
            'reset_period' => 'hourly', // invalid
        ]);

        [$start, $end] = $resolver->forFeature($feature, CarbonImmutable::parse('2026-01-09'));

        $this->assertSame('1970-01-01', $start);
        $this->assertSame('9999-12-31', $end);
    }
}
