<?php

namespace App\Services;

use App\Models\RoomRateDailyPriceOverride;
use App\Models\RoomType;
use App\Models\RoomTypeDailyOverride;

class DailyOverrideCleanupService
{
    public function deleteRange(RoomType $roomType, string $dateFrom, string $dateTo): int
    {
        if ($dateTo < $dateFrom) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $rateIds = $roomType->rates()->pluck('id');

        RoomRateDailyPriceOverride::query()
            ->whereIn('room_rate_id', $rateIds)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->delete();

        return RoomTypeDailyOverride::query()
            ->where('room_type_id', $roomType->id)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->delete();
    }

    public function deleteSingle(RoomType $roomType, RoomTypeDailyOverride $override): void
    {
        $dateStr = $override->date->toDateString();
        $this->deleteRange($roomType, $dateStr, $dateStr);
    }
}
