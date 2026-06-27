<?php

namespace App\Services;

use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomType;

class RoomSyncService
{
    /**
     * Ensure physical room records match room_type.room_count.
     */
    public function syncFromRoomType(RoomType $roomType): void
    {
        $target = max(1, (int) $roomType->room_count);
        $rooms = $roomType->rooms()->orderBy('sort_order')->orderBy('id')->get();

        while ($rooms->count() < $target) {
            $n = $rooms->count() + 1;
            $roomType->rooms()->create([
                'name'        => $this->defaultName($roomType, $n),
                'amenities'   => array_values(array_filter($roomType->amenities ?? [])),
                'sort_order'  => $n - 1,
                'is_active'   => true,
            ]);
            $rooms = $roomType->rooms()->orderBy('sort_order')->orderBy('id')->get();
        }

        while ($rooms->count() > $target) {
            /** @var Room $room */
            $room = $rooms->last();
            if ($this->hasActiveBookings($room)) {
                break;
            }
            $room->delete();
            $rooms = $roomType->rooms()->orderBy('sort_order')->orderBy('id')->get();
        }
    }

    /**
     * @param  array<int, array{id?:int, name?:string, description?:string, amenities?:array}>  $roomsData
     */
    public function updateRooms(RoomType $roomType, array $roomsData): void
    {
        $this->syncFromRoomType($roomType->fresh());

        foreach ($roomsData as $index => $data) {
            if (empty($data['id'])) {
                continue;
            }

            $room = $roomType->rooms()->find($data['id']);
            if (!$room) {
                continue;
            }

            $room->update([
                'name'        => trim((string) ($data['name'] ?? $room->name)) ?: $room->name,
                'description' => $data['description'] ?? null,
                'amenities'   => array_values(array_filter($data['amenities'] ?? [])),
                'sort_order'  => $index,
            ]);
        }
    }

    /**
     * Backfill physical rooms for all room types that have none.
     */
    public function backfillAll(): int
    {
        $count = 0;
        RoomType::query()->each(function (RoomType $roomType) use (&$count) {
            if ($roomType->rooms()->exists()) {
                return;
            }
            $this->syncFromRoomType($roomType);
            $count++;
        });

        return $count;
    }

    private function defaultName(RoomType $roomType, int $number): string
    {
        $base = trim($roomType->name) ?: ($roomType->bed_type ?: 'اتاق');

        return $base . ' ' . $number;
    }

    private function hasActiveBookings(Room $room): bool
    {
        return BookingRoom::query()
            ->where('room_id', $room->id)
            ->whereHas('booking', fn ($q) => $q->whereIn('status', ['confirmed', 'pending']))
            ->exists();
    }
}
