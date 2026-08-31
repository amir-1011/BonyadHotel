<?php

namespace App\Support;

use Carbon\CarbonInterface;

class PersianRelativeTime
{
    public static function diffForHumans(?CarbonInterface $moment): ?string
    {
        if (!$moment) {
            return null;
        }

        return $moment->copy()->locale('fa')->diffForHumans();
    }
}
