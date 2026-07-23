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

    /** @var int Max upload size per image in kilobytes (20 MB). */
    public const MAX_UPLOAD_KILOBYTES = 20480;

    /** @var int Maximum number of images per upload field. */
    public const MAX_FILES_PER_REQUEST = 8;

    /** @var int Maximum total images stored on a single accommodation. */
    public const MAX_TOTAL_IMAGES = 30;

    /**
     * @return array<int, string>
     */
    public static function fileRules(bool $nullable = true): array
    {
        $rules = ['image', 'max:' . self::MAX_UPLOAD_KILOBYTES];

        array_unshift($rules, $nullable ? 'nullable' : 'required');

        return $rules;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function manyFileRules(string $attribute, bool $nullable = true): array
    {
        $collectionRules = array_values(array_filter([
            $nullable ? 'nullable' : 'required',
            'array',
            'max:' . self::MAX_FILES_PER_REQUEST,
        ]));

        return [
            $attribute => $collectionRules,
            $attribute . '.*' => self::fileRules($nullable),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function temporaryUploadRules(): array
    {
        return ['required', 'file', 'image', 'max:' . self::MAX_UPLOAD_KILOBYTES];
    }

    public static function helpText(): string
    {
        return 'حداکثر ' . self::MAX_FILES_PER_REQUEST . ' عکس، هر کدام تا ۲۰ مگابایت';
    }

    public static function acceptAttribute(): string
    {
        return 'image/*';
    }

    public static function maxBytes(): int
    {
        return self::MAX_UPLOAD_KILOBYTES * 1024;
    }

    public static function assertTotalImageCount(int $count): void
    {
        if ($count > self::MAX_TOTAL_IMAGES) {
            throw new RuntimeException(
                'حداکثر ' . self::MAX_TOTAL_IMAGES . ' تصویر برای هر اقامتگاه مجاز است.'
            );
        }
    }

    public function storeWebp(
        UploadedFile $file,
        string $directory,
        int $quality = self::DEFAULT_QUALITY,
        int $maxWidth = self::DEFAULT_MAX_WIDTH,
        int $maxHeight = self::DEFAULT_MAX_HEIGHT
    ): string
    {
        $path = $file->getRealPath() ?: $file->getPathname();
        $this->ensureMemoryForProcessing($path);

        try {
            $manager = $this->makeManager();
            $image = $manager->read($path)->orient();

            if ($maxWidth > 0 || $maxHeight > 0) {
                $image = $image->scaleDown(width: $maxWidth, height: $maxHeight);
            }

            $encoded = $image->toWebp(quality: $quality, strip: true);
        } catch (\Throwable $e) {
            if ($this->isMemoryExhausted($e)) {
                throw new RuntimeException(
                    'پردازش این تصویر به دلیل ابعاد زیاد به حافظه بیشتری نیاز دارد. لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.',
                    previous: $e
                );
            }

            throw $e;
        }

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

    public function storeUploadedFile(UploadedFile $file, string $directory): string
    {
        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            return $this->storeWebp($file, $directory);
        }

        return $file->store(trim($directory, '/'), 'public');
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

    private function ensureMemoryForProcessing(string $path): void
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return;
        }

        [$width, $height] = $info;
        $pixels = $width * $height;

        $requiredMb = match (true) {
            $pixels > 24_000_000 => 384,
            $pixels > 12_000_000 => 256,
            $pixels > 6_000_000 => 192,
            default => 128,
        };

        $currentBytes = $this->memoryLimitToBytes(ini_get('memory_limit'));
        $requiredBytes = $requiredMb * 1024 * 1024;

        if ($currentBytes > 0 && $currentBytes < $requiredBytes) {
            @ini_set('memory_limit', $requiredMb . 'M');
        }
    }

    private function memoryLimitToBytes(string|false $limit): int
    {
        if ($limit === false || $limit === '' || $limit === '-1') {
            return -1;
        }

        $limit = trim($limit);
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function isMemoryExhausted(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'allowed memory size')
            || str_contains($message, 'memory exhausted');
    }
}
