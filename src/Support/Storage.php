<?php

namespace MichaelLurquin\FeatureLimiter\Support;

use InvalidArgumentException;

class Storage
{
    /**
     * Convertit une string type "500MB", "1GB", "1.5GB", "1024KB" en bytes (int).
     */
    public static function toBytes(string $value): int
    {
        $v = strtoupper(str_replace(' ', '', trim($value)));

        if ( !preg_match('/^(\d+(?:\.\d+)?)(B|KB|MB|GB|TB)$/', $v, $m) )
        {
            throw new InvalidArgumentException("Invalid storage value: {$value}");
        }

        $number = (float) $m[1];
        $unit = $m[2];

        $mult = match ($unit) {
            'B'  => 1,
            'KB' => 1024,
            'MB' => 1024 ** 2,
            'GB' => 1024 ** 3,
            'TB' => 1024 ** 4,
        };

        $bytes = (int) round($number * $mult);

        if ( $bytes < 0 )
        {
            throw new InvalidArgumentException("Invalid storage value: {$value}");
        }

        return $bytes;
    }

    /**
     * Convertit des bytes en string lisible (base 1024).
     * Exemple: 1073741824 => "1GB"
     */
    public static function fromBytes(int $bytes): string
    {
        if ( $bytes < 0 ) return '0B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $val = (float) $bytes;

        while ($val >= 1024 && $i < count($units) - 1)
        {
            $val /= 1024;
            $i++;
        }

        // 1 décimale max si nécessaire
        $formatted = (abs($val - round($val)) < 0.00001)
            ? (string) (int) round($val)
            : rtrim(rtrim(number_format($val, 1, '.', ''), '0'), '.');

        return $formatted . $units[$i];
    }
}
