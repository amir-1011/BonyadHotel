<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class ProgramDocumentService
{
    public const MAX_UPLOAD_KILOBYTES = 10240;

    /** @var list<string> */
    public const DOCUMENT_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    /** @var list<string> */
    public const SPREADSHEET_EXTENSIONS = ['csv', 'txt', 'xlsx', 'xls'];

    /** @var list<string> */
    public const TEMPORARY_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'heic', 'heif',
        'pdf', 'csv', 'txt', 'xlsx', 'xls',
    ];

    /** @return array<int, string> */
    public static function fileRules(bool $nullable = true): array
    {
        $rules = ['file', 'max:' . self::MAX_UPLOAD_KILOBYTES];
        array_unshift($rules, $nullable ? 'nullable' : 'required');
        $rules[] = self::extensionRule(self::DOCUMENT_EXTENSIONS, 'فرمت مجاز: PDF یا تصویر (JPG, PNG, WEBP)');

        return $rules;
    }

    /** @return array<int, string> */
    public static function spreadsheetRules(bool $nullable = true): array
    {
        $rules = ['file', 'max:' . self::MAX_UPLOAD_KILOBYTES];
        array_unshift($rules, $nullable ? 'nullable' : 'required');
        $rules[] = self::extensionRule(self::SPREADSHEET_EXTENSIONS, 'فرمت مجاز: Excel (xlsx, xls) یا CSV');

        return $rules;
    }

    /**
     * Livewire temporary upload rules — must allow PDF/spreadsheets, not only images.
     *
     * Uses string rules only so config:cache can serialize livewire.php.
     *
     * @return array<int, string>
     */
    public static function temporaryUploadRules(): array
    {
        return [
            'required',
            'file',
            'max:' . ImageUploadService::MAX_UPLOAD_KILOBYTES,
            'extensions:' . implode(',', self::TEMPORARY_EXTENSIONS),
        ];
    }

    /**
     * @param  list<string>  $allowedExtensions
     */
    private static function extensionRule(array $allowedExtensions, string $message): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail) use ($allowedExtensions, $message): void {
            if (!$value instanceof UploadedFile) {
                return;
            }

            $extension = strtolower($value->getClientOriginalExtension());
            if (!in_array($extension, $allowedExtensions, true)) {
                $fail($message);
            }
        };
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     * @return array<int, string>
     */
    public function storeMany(array $files, string $directory): array
    {
        $paths = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $stored = $this->storeOne($file, $directory);
            if ($stored !== null) {
                $paths[] = $stored;
            }
        }

        return $paths;
    }

    public function storeOne(UploadedFile $file, string $directory): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $name = Str::uuid()->toString() . ($extension !== '' ? '.' . $extension : '');

        return $file->storeAs(trim($directory, '/'), $name, 'public');
    }

    /**
     * @param  array<int, string>  $paths
     */
    public function deleteMany(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('public')->delete($path);
            }
        }
    }

    public function publicUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
