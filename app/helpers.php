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

        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $latin   = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $normalized = str_replace($persian, $latin, (string) $value);
        $digits = preg_replace('/[^\d]/', '', $normalized);

        return $digits === '' ? null : (int) $digits;
    }
}
