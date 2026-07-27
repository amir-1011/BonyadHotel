<?php

namespace App\Support;

class AssetVersion
{
    public static function path(): string
    {
        return storage_path('framework/asset_version');
    }

    public static function current(): string
    {
        $path = static::path();

        if (is_file($path)) {
            $version = trim((string) file_get_contents($path));
            if ($version !== '') {
                return $version;
            }
        }

        return (string) env('ASSET_VERSION', '1');
    }

    public static function bump(): string
    {
        $version = (string) time();
        $path = static::path();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $version, LOCK_EX);

        return $version;
    }

    public static function shouldVersion(string $path): bool
    {
        $path = ltrim($path, '/');

        return str_starts_with($path, 'vendor/')
            || str_starts_with($path, 'logo/')
            || str_starts_with($path, 'images/');
    }

    public static function url(string $path, ?bool $secure = null): string
    {
        $url = asset($path, $secure);

        if (! static::shouldVersion($path)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'v='.rawurlencode(static::current());
    }
}
