<?php

use App\Support\AssetVersion;

if (! function_exists('vasset')) {
    function vasset(string $path, ?bool $secure = null): string
    {
        return AssetVersion::url($path, $secure);
    }
}

if (! function_exists('parse_money_input')) {
    function parse_money_input(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = \App\Support\PdfPersian::toEnglishDigits((string) $value);
        $digits = preg_replace('/[^\d]/', '', $normalized);

        return $digits === '' ? null : (int) $digits;
    }
}
