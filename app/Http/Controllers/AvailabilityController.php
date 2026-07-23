<?php

namespace App\Http\Controllers;

use App\Models\Accommodation;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Services\RoomAvailabilityService;
use App\Support\StayDurationPicker;
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

        $roomRate = null;
        if ($request->filled('room_rate_id')) {
            $roomRate = RoomRate::query()
                ->where('id', (int) $request->input('room_rate_id'))
                ->where('room_type_id', $roomType->id)
                ->first();
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
                $rangeEnd->format('Y-m-d'),
                $roomRate,
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

        // Limit range length to prevent abuse
        if ($this->isStayRangeTooLong($checkIn, $checkOut)) {
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

    /**
     * Per-physical-room availability for manual booking room picker.
     *
     * GET /api/room-types/{roomType}/physical-rooms?check_in=Y-m-d&check_out=Y-m-d&exclude_room_ids=1,2
     */
    public function physicalRooms(Request $request, RoomType $roomType, RoomAvailabilityService $service): JsonResponse
    {
        if (!$roomType->is_active || !$roomType->accommodation->is_active) {
            return response()->json(['rooms' => []]);
        }

        $checkIn  = $request->input('check_in');
        $checkOut = $request->input('check_out');

        if (!$checkIn || !$checkOut || $checkIn >= $checkOut) {
            return response()->json(['rooms' => [], 'error' => 'invalid_dates'], 422);
        }

        if ($this->isStayRangeTooLong($checkIn, $checkOut)) {
            return response()->json(['rooms' => [], 'error' => 'range_too_long'], 422);
        }

        $excludeIds = array_filter(array_map('intval', explode(',', (string) $request->input('exclude_room_ids', ''))));

        $rooms = $service->roomsForRange($roomType, $checkIn, $checkOut, $excludeIds);

        return response()->json([
            'rooms'      => $rooms,
            'room_type'  => [
                'id'   => $roomType->id,
                'name' => $roomType->name,
            ],
        ]);
    }

    public function accommodationPhysicalRooms(
        Request $request,
        Accommodation $accommodation,
        RoomAvailabilityService $service,
    ): JsonResponse {
        if (!$accommodation->is_active) {
            return response()->json(['rooms' => []]);
        }

        $checkIn  = $request->input('check_in');
        $checkOut = $request->input('check_out');

        if (!$checkIn || !$checkOut || $checkIn >= $checkOut) {
            return response()->json(['rooms' => [], 'error' => 'invalid_dates'], 422);
        }

        if ($this->isStayRangeTooLong($checkIn, $checkOut)) {
            return response()->json(['rooms' => [], 'error' => 'range_too_long'], 422);
        }

        $excludeIds = array_filter(array_map('intval', explode(',', (string) $request->input('exclude_room_ids', ''))));

        return response()->json([
            'rooms' => $service->roomsForAccommodation($accommodation, $checkIn, $checkOut, $excludeIds),
        ]);
    }

    private function isStayRangeTooLong(string $checkIn, string $checkOut): bool
    {
        return (new \DateTime($checkIn))->diff(new \DateTime($checkOut))->days > StayDurationPicker::MAX_NIGHTS;
    }
}
