<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use RuntimeException;

class ImageUploadService
{
    private const DEFAULT_QUALITY = 75;
    private const DEFAULT_MAX_WIDTH = 1600;
    private const DEFAULT_MAX_HEIGHT = 1600;

    public function storeWebp(
        UploadedFile $file,
        string $directory,
        int $quality = self::DEFAULT_QUALITY,
        int $maxWidth = self::DEFAULT_MAX_WIDTH,
        int $maxHeight = self::DEFAULT_MAX_HEIGHT
    ): string
    {
        $manager = $this->makeManager();

        $path = $file->getRealPath() ?: $file->getPathname();
        $image = $manager->read($path);

        if ($maxWidth > 0 || $maxHeight > 0) {
            $image = $image->scaleDown(width: $maxWidth, height: $maxHeight);
        }

        $encoded = $image->toWebp(quality: $quality, strip: true);

        $directory = trim($directory, '/');
        $relativePath = $directory . '/' . Str::uuid()->toString() . '.webp';

        Storage::disk('public')->put($relativePath, $encoded->toString());

        return $relativePath;
    }

    /**
     * @param array<int, UploadedFile> $files
     * @return array<int, string>
     */
    public function storeManyWebp(
        array $files,
        string $directory,
        int $quality = self::DEFAULT_QUALITY,
        int $maxWidth = self::DEFAULT_MAX_WIDTH,
        int $maxHeight = self::DEFAULT_MAX_HEIGHT
    ): array
    {
        $stored = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $stored[] = $this->storeWebp($file, $directory, $quality, $maxWidth, $maxHeight);
            }
        }

        return $stored;
    }

    private function makeManager(): ImageManager
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            return ImageManager::imagick();
        }

        if (extension_loaded('gd')) {
            return ImageManager::gd();
        }

        throw new RuntimeException('An image processing extension (GD or Imagick) is required to process uploads.');
    }
}
