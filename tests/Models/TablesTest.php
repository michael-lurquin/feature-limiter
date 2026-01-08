<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Models;

use MichaelLurquin\FeatureLimiter\Models\Plan;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;
use MichaelLurquin\FeatureLimiter\Models\PlanFeature;
use MichaelLurquin\FeatureLimiter\Models\FeatureUsage;

class TablesTest extends TestCase
{
    public function test_models_use_configured_tables(): void
    {
        $this->assertSame('fl_features', (new Feature)->getTable());
        $this->assertSame('fl_plans', (new Plan)->getTable());
        $this->assertSame('fl_feature_plan', (new PlanFeature)->getTable());
        $this->assertSame('fl_feature_usages', (new FeatureUsage)->getTable());
    }
}
