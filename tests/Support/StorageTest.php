<?php

namespace MichaelLurquin\FeatureLimiter\Tests\Support;

use InvalidArgumentException;
use MichaelLurquin\FeatureLimiter\Support\Storage;
use MichaelLurquin\FeatureLimiter\Tests\TestCase;

class StorageTest extends TestCase
{
    public function test_to_bytes_parses_units_and_decimals(): void
    {
        $this->assertSame(500, Storage::toBytes('500B'));
        $this->assertSame(1024, Storage::toBytes('1KB'));
        $this->assertSame(1536, Storage::toBytes('1.5KB'));
        $this->assertSame(1073741824, Storage::toBytes('1GB'));
        $this->assertSame(1610612736, Storage::toBytes('1.5GB'));
    }

    public function test_from_bytes_formats_units(): void
    {
        $this->assertSame('0B', Storage::fromBytes(0));
        $this->assertSame('1KB', Storage::fromBytes(1024));
        $this->assertSame('1.5KB', Storage::fromBytes(1536));
        $this->assertSame('1GB', Storage::fromBytes(1024 ** 3));
    }

    public function test_to_bytes_rejects_invalid_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Storage::toBytes('10XB');
    }
}
