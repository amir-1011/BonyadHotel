<?php

namespace App\Support;

use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

class JalaliDateTimeInput
{
    public static function nowJalaliDate(): string
    {
        return Jalalian::now()->format('Y/m/d');
    }

    public static function nowTime(): string
    {
        return now()->format('H:i');
    }

    public static function normalizeDate(string $jalali): string
    {
        return strtr(PdfPersian::toEnglishDigits(trim($jalali)), ['-' => '/']);
    }

    public static function toGregorianDate(?string $jalali): ?string
    {
        if (!$jalali) {
            return null;
        }

        try {
            $date = self::normalizeDate($jalali);

            return Jalalian::fromFormat('Y/m/d', $date)->toCarbon()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function toCarbon(string $jalaliDate, string $time): Carbon
    {
        $date = self::normalizeDate($jalaliDate);
        $carbon = Jalalian::fromFormat('Y/m/d', $date)->toCarbon();

        $timeDigits = preg_replace('/[^\d:]/', '', PdfPersian::toEnglishDigits($time)) ?? '';
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $timeDigits, $matches)) {
            throw new \InvalidArgumentException('زمان واریز معتبر نیست.');
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            throw new \InvalidArgumentException('زمان واریز معتبر نیست.');
        }

        return $carbon->setTime($hour, $minute, 0);
    }
}
