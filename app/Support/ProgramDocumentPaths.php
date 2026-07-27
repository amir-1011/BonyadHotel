<?php

declare(strict_types=1);

namespace App\Support;

final class ProgramDocumentPaths
{
    /** @return list<string> */
    public static function normalize(mixed $documents): array
    {
        if (is_string($documents) && $documents !== '') {
            return [$documents];
        }

        if (!is_array($documents)) {
            return [];
        }

        $paths = [];

        foreach ($documents as $item) {
            if (is_string($item) && $item !== '') {
                $paths[] = self::stripStoragePrefix($item);
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            foreach (['path', 'url', 'file'] as $key) {
                if (isset($item[$key]) && is_string($item[$key]) && $item[$key] !== '') {
                    $paths[] = self::stripStoragePrefix($item[$key]);
                    break;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    public static function count(mixed $documents): int
    {
        return count(self::normalize($documents));
    }

    private static function stripStoragePrefix(string $path): string
    {
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            return substr($path, strlen('storage/'));
        }

        return $path;
    }
}
