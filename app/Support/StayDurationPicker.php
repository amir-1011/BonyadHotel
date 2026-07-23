<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Stay-night range helpers shared with manual booking duration picker (JS bnbStayPicker).
 */
class StayDurationPicker
{
    public const MAX_NIGHTS = 365;

    public static function checkOutFromNights(string $checkIn, int $nights): string
    {
        if ($checkIn === '' || $nights < 1) {
            return '';
        }

        return Carbon::parse($checkIn)->addDays($nights)->format('Y-m-d');
    }

    public static function nightsBetween(string $checkIn, string $checkOut): int
    {
        if ($checkIn === '' || $checkOut === '' || $checkOut <= $checkIn) {
            return 0;
        }

        return Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut));
    }

    public static function lastStayNight(string $checkIn, string $checkOut): string
    {
        if ($checkIn === '' || $checkOut === '' || $checkOut <= $checkIn) {
            return '';
        }

        return Carbon::parse($checkOut)->subDay()->format('Y-m-d');
    }

    /**
     * @param  callable(string): bool  $isNightInvalid  Receives each stay-night gregorian date.
     */
    public static function hasInvalidNightInRange(string $checkIn, string $lastNight, callable $isNightInvalid): bool
    {
        if ($checkIn === '' || $lastNight === '' || $lastNight < $checkIn) {
            return true;
        }

        $cursor = Carbon::parse($checkIn);
        $end = Carbon::parse($lastNight);

        while ($cursor->lte($end)) {
            if ($isNightInvalid($cursor->format('Y-m-d'))) {
                return true;
            }
            $cursor->addDay();
        }

        return false;
    }

    /**
     * @return array{0:bool,1:?string} [valid, errorMessage]
     */
    public static function validateNightsInput(int $nights): array
    {
        if ($nights < 1) {
            return [false, 'لطفاً تعداد شب اقامت را وارد کنید (حداقل ۱ شب).'];
        }

        if ($nights > self::MAX_NIGHTS) {
            return [false, 'حداکثر مدت اقامت ' . self::MAX_NIGHTS . ' شب است.'];
        }

        return [true, null];
    }
}
