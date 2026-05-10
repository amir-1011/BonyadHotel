<?php

namespace App\Http\Controllers;

use App\Models\Accommodation;
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

    /**
     * Return min available rooms for every active room type in an accommodation
     * for a given check_in / check_out range. Used by the public show page to
     * display availability badges on room cards.
     *
     * GET /api/accommodations/{accommodation}/rooms-availability?check_in=Y-m-d&check_out=Y-m-d
     */
    public function accommodationRooms(Request $request, Accommodation $accommodation): JsonResponse
    {
        if (!$accommodation->is_active) {
            return response()->json([]);
        }

        $checkIn  = $request->input('check_in');
        $checkOut = $request->input('check_out');

        if (!$checkIn || !$checkOut || $checkIn >= $checkOut) {
            return response()->json([]);
        }

        // Limit to 90 days to prevent abuse
        if ((new \DateTime($checkIn))->diff(new \DateTime($checkOut))->days > 90) {
            return response()->json([]);
        }

        $result = [];
        foreach ($accommodation->roomTypes()->where('is_active', true)->get() as $rt) {
            $map      = $rt->availabilityMap($checkIn, $checkOut);
            $entries  = collect($map);
            $minAvail = (int) $entries->min('available_rooms');
            $hasBlock = $entries->contains('is_blocked', true);
            $result[$rt->id] = [
                'min_available' => $minAvail,
                'is_available'  => !$hasBlock && $minAvail > 0,
                'room_count'    => (int) $rt->room_count,
                'capacity'      => (int) $rt->capacity,
            ];
        }

        return response()->json($result);
    }
}
