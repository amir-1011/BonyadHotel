<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomType;

class RoomAvailabilityService
{
    /**
     * Per-physical-room availability for a stay range.
     *
     * @param  array<int>  $excludeRoomIds  Rooms already picked in current draft booking
     * @param  int|null  $excludeBookingId  Ignore lines from this booking (edit flows)
     * @return array<int, array<string, mixed>>
     */
    public function roomsForRange(
        RoomType $roomType,
        string $checkIn,
        string $checkOut,
        array $excludeRoomIds = [],
        ?int $excludeBookingId = null,
    ): array {
        $rooms = $roomType->rooms()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rooms->isEmpty()) {
            return [];
        }

        $typeMap = $roomType->availabilityMap($checkIn, $checkOut);
        $blockedIndex = $roomType->blockedDatesIndex($checkIn, $checkOut);
        $bookedRoomIds = $this->bookedRoomIdsForRange($roomType, $checkIn, $checkOut, $excludeBookingId);
        $excludeRoomIds = array_flip(array_map('intval', $excludeRoomIds));

        $result = [];
        foreach ($rooms as $index => $room) {
            $status = $this->resolveStatus($room, $index, $typeMap, $blockedIndex, $bookedRoomIds, $excludeRoomIds);
            $result[] = [
                'id'             => $room->id,
                'name'           => $room->name,
                'description'    => $room->description,
                'amenities'      => $room->displayAmenities(),
                'sort_order'     => $room->sort_order,
                'room_type_id'   => $roomType->id,
                'room_type_name' => $roomType->name,
                'status'         => $status,
                'selectable'     => $status === 'available',
                'status_label'   => $this->statusLabel($status),
                'color'          => $this->statusColor($status),
            ];
        }

        return $result;
    }

    /**
     * @param  array<int>  $excludeRoomIds
     * @return array<int, array<string, mixed>>
     */
    public function roomsForAccommodation(
        \App\Models\Accommodation $accommodation,
        string $checkIn,
        string $checkOut,
        array $excludeRoomIds = [],
        ?int $excludeBookingId = null,
    ): array {
        $result = [];

        $roomTypes = $accommodation->roomTypes()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($roomTypes as $roomType) {
            foreach ($this->roomsForRange($roomType, $checkIn, $checkOut, $excludeRoomIds, $excludeBookingId) as $room) {
                $room['room_type_id'] = $roomType->id;
                $room['room_type_name'] = $roomType->name;
                $result[] = $room;
            }
        }

        return $result;
    }

    public function isRoomAvailable(
        Room $room,
        string $checkIn,
        string $checkOut,
        array $excludeRoomIds = [],
        ?int $excludeBookingId = null,
    ): bool {
        $roomType = $room->roomType;
        if (!$roomType || !$room->is_active) {
            return false;
        }

        $rooms = $this->roomsForRange($roomType, $checkIn, $checkOut, $excludeRoomIds, $excludeBookingId);
        $match = collect($rooms)->firstWhere('id', $room->id);

        return $match && ($match['selectable'] ?? false);
    }

    /**
     * @param  array<string, array<string, mixed>>  $typeMap
     * @param  array<string, array{all?:bool, room_ids?:array<int, true>}>  $blockedIndex
     * @param  array<int, true>  $bookedRoomIds
     * @param  array<int, true>  $excludeRoomIds
     */
    private function resolveStatus(
        Room $room,
        int $index,
        array $typeMap,
        array $blockedIndex,
        array $bookedRoomIds,
        array $excludeRoomIds,
    ): string {
        if (isset($excludeRoomIds[$room->id])) {
            return 'picked';
        }

        if (isset($bookedRoomIds[$room->id])) {
            return 'booked';
        }

        foreach ($typeMap as $dateStr => $day) {
            $effectiveTotal = (int) ($day['total'] ?? $room->roomType->room_count);
            if ($room->roomType->isRoomBlockedOnDate($room->id, $dateStr, $effectiveTotal, $blockedIndex)) {
                return 'blocked';
            }

            if ($index >= $effectiveTotal) {
                return 'capacity_closed';
            }
        }

        return 'available';
    }

    /**
     * Physical rooms with an active booking overlapping [checkIn, checkOut).
     *
     * @return array<int, true>
     */
    public function bookedRoomIdsForRange(
        RoomType $roomType,
        string $checkIn,
        string $checkOut,
        ?int $excludeBookingId = null,
    ): array {
        $query = BookingRoom::query()
            ->where('room_type_id', $roomType->id)
            ->whereNotNull('room_id')
            ->whereHas('booking', function ($q) use ($checkIn, $checkOut, $excludeBookingId) {
                $q->whereIn('status', ['confirmed', 'pending'])
                    ->where('check_in', '<', $checkOut)
                    ->where('check_out', '>', $checkIn);
                if ($excludeBookingId) {
                    $q->where('id', '!=', $excludeBookingId);
                }
            });

        return $query->pluck('room_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'available'        => 'آزاد',
            'booked'           => 'رزرو شده',
            'blocked'          => 'مسدود',
            'capacity_closed'  => 'بسته (سیاست قیمتی)',
            'picked'           => 'انتخاب‌شده در این رزرو',
            default            => $status,
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'available'        => 'success',
            'booked'           => 'secondary',
            'blocked'          => 'danger',
            'capacity_closed'  => 'warning',
            'picked'           => 'info',
            default            => 'secondary',
        };
    }
}
