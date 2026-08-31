<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use Morilog\Jalali\Jalalian;
use Zarbinco\PersianCore\Facades\Persian;

class PdfPersian
{
    public static function toPersianDigits(string $text): string
    {
        return Persian::number($text)->toPersian();
    }

    public static function toEnglishDigits(string $text): string
    {
        return Persian::number($text)->toEnglish();
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
        return self::toPersianDigits(number_format((int) $value)) . ' ریال';
    }
}
