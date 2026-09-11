<?php

namespace App\Support;

class IranIban
{
    public static function normalize(?string $value): string
    {
        $value = PdfPersian::toEnglishDigits(trim((string) $value));
        $value = strtoupper((string) preg_replace('/\s+/', '', $value));

        return $value;
    }

    public static function isValid(?string $value): bool
    {
        return (bool) preg_match('/^IR\d{24}$/', self::normalize($value));
    }
}
