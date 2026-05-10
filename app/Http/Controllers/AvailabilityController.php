<?php

namespace App\Http\Controllers;

use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    /**
     * Return the availability map for a given room type over the requested months.
     *
     * Query parameters:
     *   months  – comma-separated list of YYYY-MM strings, e.g. "2026-05,2026-06"
     *             Defaults to the current month + next 2 months.
     *
     * Response:
     *   {
     *     "dates": {
     *       "2026-05-10": { "total": 3, "booked": 3, "available_rooms": 0, "is_blocked": false },
     *       ...
     *     }
     *   }
     */
    public function roomType(Request $request, RoomType $roomType): JsonResponse
    {
        // Only expose availability for active room types in active accommodations
        if (!$roomType->is_active || !$roomType->accommodation->is_active) {
            return response()->json(['dates' => []]);
        }

        $monthsParam = $request->input('months');

        if ($monthsParam) {
            $monthList = array_slice(
                array_filter(array_map('trim', explode(',', $monthsParam))),
                0, 6  // Max 6 months at once
            );
        } else {
            // Default: current + next 2 months
            $monthList = [];
            $now = new \DateTime('first day of this month');
            for ($i = 0; $i < 3; $i++) {
                $monthList[] = $now->format('Y-m');
                $now->modify('+1 month');
            }
        }

        $dates = [];
        $today = new \DateTime('today');

        foreach ($monthList as $ym) {
            if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
                continue;
            }
            [$year, $month] = explode('-', $ym);
            $from = new \DateTime("$year-$month-01");
            // Only include from today onwards
            $rangeStart = max($from, clone $today);
            $rangeEnd   = (clone $from)->modify('last day of this month')->modify('+1 day');

            if ($rangeStart >= $rangeEnd) {
                continue;
            }

            $chunk = $roomType->availabilityMap(
                $rangeStart->format('Y-m-d'),
                $rangeEnd->format('Y-m-d')
            );
            $dates = array_merge($dates, $chunk);
        }

        return response()->json(['dates' => $dates]);
    }
}
