<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Support;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;
use MichaelLurquin\FeatureLimiter\Support\FeatureValueParser;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;

class FeatureValueParserTest extends TestCase
{
    public function test_it_parses_boolean_values(): void
    {
        $feature = new Feature(['key' => 'custom_code']);
        $feature->type = FeatureType::BOOLEAN;

        $parser = new FeatureValueParser();

        $this->assertSame(['1', false], $parser->parse($feature, true));
        $this->assertSame(['0', false], $parser->parse($feature, false));
        $this->assertSame(['1', false], $parser->parse($feature, 'yes'));
        $this->assertSame(['0', false], $parser->parse($feature, 'no'));
    }

    public function test_it_rejects_invalid_boolean_values(): void
    {
        $feature = new Feature(['key' => 'custom_code']);
        $feature->type = FeatureType::BOOLEAN;

        $parser = new FeatureValueParser();

        $this->expectException(InvalidArgumentException::class);

        $parser->parse($feature, 'maybe');
    }

    public function test_it_parses_integer_values(): void
    {
        $feature = new Feature(['key' => 'sites']);
        $feature->type = FeatureType::INTEGER;

        $parser = new FeatureValueParser();

        $this->assertSame(['3', false], $parser->parse($feature, 3));
        $this->assertSame(['3', false], $parser->parse($feature, '003'));
        $this->assertSame(['0', false], $parser->parse($feature, '0'));
    }

    public function test_it_rejects_negative_integer_values(): void
    {
        $feature = new Feature(['key' => 'sites']);
        $feature->type = FeatureType::INTEGER;

        $parser = new FeatureValueParser();

        $this->expectException(InvalidArgumentException::class);

        $parser->parse($feature, -2);
    }

    public function test_it_parses_storage_values(): void
    {
        $feature = new Feature(['key' => 'storage']);
        $feature->type = FeatureType::STORAGE;

        $parser = new FeatureValueParser();

        $this->assertSame(['1024B', false], $parser->parse($feature, 1024));
        $this->assertSame(['1GB', false], $parser->parse($feature, '1gb'));
        $this->assertSame(['1.5GB', false], $parser->parse($feature, '1.5GB'));
    }

    public function test_it_parses_unlimited_values(): void
    {
        $feature = new Feature(['key' => 'storage']);
        $feature->type = FeatureType::STORAGE;

        $parser = new FeatureValueParser();

        $this->assertSame([null, true], $parser->parse($feature, null));
        $this->assertSame([null, true], $parser->parse($feature, -1));
        $this->assertSame([null, true], $parser->parse($feature, 'unlimited'));
        $this->assertSame([null, true], $parser->parse($feature, '1GB', true));
    }

    public function test_boolean_cannot_be_unlimited(): void
    {
        $feature = new Feature(['key' => 'custom_code']);
        $feature->type = FeatureType::BOOLEAN;

        $parser = new FeatureValueParser();

        $this->expectException(InvalidArgumentException::class);

        $parser->parse($feature, null);
    }
}
