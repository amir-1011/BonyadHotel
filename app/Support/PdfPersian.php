<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Morilog\Jalali\Jalalian;

class PdfPersian
{
    public static function toPersianDigits(string $text): string
    {
        return strtr($text, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }

    public static function jalali(DateTimeInterface|string|null $date, string $format = 'Y/m/d'): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        $carbon = $date instanceof DateTimeInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);

        return self::toPersianDigits(Jalalian::fromCarbon($carbon)->format($format));
    }

    public static function amount(int|float|string $value): string
    {
        return self::toPersianDigits(number_format((int) $value)) . ' تومان';
    }
}
