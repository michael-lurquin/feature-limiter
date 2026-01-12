<?php

namespace MichaelLurquin\FeatureLimiter\Support;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Models\Feature;
use MichaelLurquin\FeatureLimiter\Enums\FeatureType;

class FeatureValueParser
{
    /**
     * @return array{0: ?string, 1: bool} [value, is_unlimited]
     */
    public function parse(Feature $feature, mixed $raw, bool $forceUnlimited = false): array
    {
        $unlimited = $this->parseUnlimited($feature, $raw, $forceUnlimited);

        if ( $unlimited !== null )
        {
            return $unlimited;
        }

        return match ($feature->type) {
            FeatureType::BOOLEAN => [$this->parseBoolean($feature->key, $raw), false],
            FeatureType::INTEGER => [$this->parseInteger($feature->key, $raw), false],
            FeatureType::STORAGE => [$this->parseStorage($feature->key, $raw), false],
        };
    }

    private function parseUnlimited(Feature $feature, mixed $raw, bool $forceUnlimited): ?array
    {
        if ( $forceUnlimited )
        {
            $this->assertNotBooleanUnlimited($feature);
            return [null, true];
        }

        if ( is_string($raw) && strtolower(trim($raw)) === 'unlimited' )
        {
            $this->assertNotBooleanUnlimited($feature);
            return [null, true];
        }

        if ( $raw === null )
        {
            $this->assertNotBooleanUnlimited($feature);
            return [null, true];
        }

        if ( $raw === -1 )
        {
            $this->assertNotBooleanUnlimited($feature);
            return [null, true];
        }

        return null;
    }

    protected function assertNotBooleanUnlimited(Feature $feature): void
    {
        if ( $feature->type === FeatureType::BOOLEAN )
        {
            throw new InvalidArgumentException("Boolean feature cannot be unlimited: {$feature->key}");
        }
    }

    protected function parseBoolean(string $key, mixed $raw): string
    {
        if ( is_bool($raw) )
        {
            return $raw ? '1' : '0';
        }

        if ( is_int($raw) )
        {
            if ( $raw === 0 || $raw === 1) return (string) $raw;

            throw new InvalidArgumentException("Invalid boolean value for {$key}: {$raw}");
        }

        if ( is_string($raw) )
        {
            $v = strtolower(trim($raw));
            $truthy = ['1', 'true', 'yes', 'y', 'on'];
            $falsy  = ['0', 'false', 'no', 'n', 'off'];

            if ( in_array($v, $truthy, true)) return '1';
            if ( in_array($v, $falsy, true)) return '0';
        }

        throw new InvalidArgumentException("Invalid boolean value for {$key}");
    }

    protected function parseInteger(string $key, mixed $raw): string
    {
        if ( is_int($raw) )
        {
            if ( $raw < 0) throw new InvalidArgumentException("Invalid integer (negative) for {$key}");

            return (string) $raw;
        }

        if ( is_string($raw) )
        {
            $raw = trim($raw);

            if ( $raw !== '' && ctype_digit($raw) )
            {
                $trim = ltrim($raw, '0');

                return $trim === '' ? '0' : $trim;
            }
        }

        throw new InvalidArgumentException("Invalid integer value for {$key}");
    }

    protected function parseStorage(string $key, mixed $raw): string
    {
        if ( is_int($raw) )
        {
            if ( $raw < 0) throw new InvalidArgumentException("Storage value for {$key} must be >= 0.");

            return $raw . 'B';
        }

        if ( !is_string($raw) )
        {
            throw new InvalidArgumentException("Storage value for {$key} must be a string like '500MB' or '1GB'.");
        }

        $v = strtoupper(trim($raw));

        if ( !preg_match('/^\d+(\.\d+)?\s*(B|KB|MB|GB|TB)$/', $v) )
        {
            throw new InvalidArgumentException("Invalid storage value for {$key}: {$raw}");
        }

        return str_replace(' ', '', $v);
    }
}
