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
        $normalized = strtr(trim($jalali), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '-' => '/',
        ]);

        return $normalized;
    }

    public static function toCarbon(string $jalaliDate, string $time): Carbon
    {
        $date = self::normalizeDate($jalaliDate);
        $carbon = Jalalian::fromFormat('Y/m/d', $date)->toCarbon();

        $timeDigits = preg_replace('/[^\d:]/', '', $time) ?? '';
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
