<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Collection;

class RoomStatusBoardService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildForHost(User $host, ?string $date = null): array
    {
        $date = $date ?: now()->toDateString();
        $accommodationIds = $host->managedAccommodationIds()->all();

        if ($accommodationIds === []) {
            return [];
        }

        return $this->buildForAccommodations($accommodationIds, $date);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildForAccommodation(int $accommodationId, ?string $date = null): array
    {
        $date = $date ?: now()->toDateString();

        return $this->buildForAccommodations([$accommodationId], $date);
    }

    /**
     * @param  array<int>  $accommodationIds
     * @return array<int, array<string, mixed>>
     */
    public function buildForAccommodations(array $accommodationIds, string $date): array
    {
        $today = now()->toDateString();

        if ($accommodationIds === []) {
            return [];
        }

        $accommodations = Accommodation::query()
            ->whereIn('id', $accommodationIds)
            ->where('is_active', true)
            ->with([
                'roomTypes' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
                'roomTypes.rooms' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
            ])
            ->orderBy('name')
            ->get();

        if ($accommodations->isEmpty()) {
            return [];
        }

        $accommodationIds = $accommodations->pluck('id')->all();
        $roomIds = $accommodations
            ->flatMap(fn ($a) => $a->roomTypes->flatMap(fn ($rt) => $rt->rooms->pluck('id')))
            ->unique()
            ->values()
            ->all();

        $linesByRoom = $this->bookingLinesByRoom($accommodationIds, $roomIds, $date);

        $layoutService = app(RoomBoardLayoutService::class);
        $result = [];
        foreach ($accommodations as $accommodation) {
            $allRooms = [];
            $groups = [];

            foreach ($accommodation->roomTypes as $roomType) {
                if ($roomType->rooms->isEmpty()) {
                    continue;
                }

                $dayMap = $roomType->availabilityMap($date, (new \DateTime($date))->modify('+1 day')->format('Y-m-d'));
                $dayInfo = $dayMap[$date] ?? null;
                $blockedIndex = $roomType->blockedDatesIndex($date, (new \DateTime($date))->modify('+1 day')->format('Y-m-d'));

                $groupRooms = [];
                foreach ($roomType->rooms as $index => $room) {
                    $card = $this->buildRoomCard(
                        $room,
                        $index,
                        $date,
                        $today,
                        $blockedIndex,
                        $dayInfo,
                        $linesByRoom->get($room->id, collect()),
                        $roomType,
                    );
                    $groupRooms[] = $card;
                    $allRooms[] = $card;
                }

                if ($groupRooms !== []) {
                    $groups[] = [
                        'room_type_id'   => $roomType->id,
                        'room_type_name' => $roomType->name,
                        'bed_type'       => $roomType->bed_type,
                        'rooms'          => $groupRooms,
                    ];
                }
            }

            if ($allRooms === []) {
                continue;
            }

            $savedLayout = $layoutService->getAccommodationLayout($accommodation);
            $organized = $layoutService->organizeRooms($allRooms, $savedLayout);

            $result[] = [
                'accommodation_id'   => $accommodation->id,
                'accommodation_name' => $accommodation->name,
                'rooms'              => $allRooms,
                'rows'               => $organized['rows'],
                'cols'               => $organized['cols'],
                'groups'             => $groups,
            ];
        }

        return $result;
    }

    /**
     * @return Collection<int, Collection<int, BookingRoom>>
     */
    private function bookingLinesByRoom(array $accommodationIds, array $roomIds, string $fromDate): Collection
    {
        if ($roomIds === []) {
            return collect();
        }

        $horizon = (new \DateTime($fromDate))->modify('+90 days')->format('Y-m-d');

        return BookingRoom::query()
            ->whereIn('room_id', $roomIds)
            ->whereHas('booking', function ($q) use ($accommodationIds, $fromDate, $horizon) {
                $q->whereIn('accommodation_id', $accommodationIds)
                    ->whereIn('status', ['confirmed', 'pending'])
                    ->where('check_in', '<', $horizon)
                    ->where('check_out', '>', $fromDate);
            })
            ->with(['booking.user', 'booking.accommodation', 'roomRate', 'roomType'])
            ->get()
            ->groupBy('room_id');
    }

    /**
     * @param  Collection<int, BookingRoom>  $lines
     * @return array<string, mixed>
     */
    private function buildRoomCard(
        Room $room,
        int $index,
        string $date,
        string $today,
        array $blockedIndex,
        ?array $dayInfo,
        Collection $lines,
        RoomType $roomType,
    ): array {
        $current = $lines->first(function (BookingRoom $line) use ($date) {
            $b = $line->booking;
            if (!$b) {
                return false;
            }
            $ci = $b->check_in->format('Y-m-d');
            $co = $b->check_out->format('Y-m-d');

            return $ci <= $date && $co > $date;
        });

        $future = $lines
            ->filter(function (BookingRoom $line) use ($date) {
                $b = $line->booking;

                return $b && $b->check_in->format('Y-m-d') > $date;
            })
            ->sortBy(fn (BookingRoom $line) => $line->booking->check_in->format('Y-m-d'))
            ->values();

        $effectiveTotal = (int) ($dayInfo['total'] ?? $roomType->room_count);
        $isRoomBlocked = $roomType->isRoomBlockedOnDate($room->id, $date, $effectiveTotal, $blockedIndex);
        $blockReason = $isRoomBlocked ? $roomType->blockReasonForRoomOnDate($room->id, $date) : null;
        $capacityClosed = !$isRoomBlocked && !$current && $index >= $effectiveTotal;

        if ($isRoomBlocked) {
            $status = 'blocked';
        } elseif ($current) {
            $status = 'occupied';
        } elseif ($capacityClosed) {
            $status = 'capacity_closed';
        } else {
            $status = 'available';
        }

        return [
            'id'              => $room->id,
            'room_type_id'    => $roomType->id,
            'room_type_name'  => $roomType->name,
            'bed_type'        => $roomType->bed_type,
            'name'            => $room->name,
            'description'  => $room->description,
            'amenities'    => $room->displayAmenities(),
            'status'       => $status,
            'status_label' => $this->statusLabel($status),
            'color'        => $this->statusColor($status),
            'has_future'   => $future->isNotEmpty() && $status === 'available',
            'block_reason' => $isRoomBlocked ? ($blockReason ?: 'مسدود توسط میزبان') : null,
            'current_booking' => $current ? $this->formatBookingLine($current) : null,
            'future_bookings' => $future->map(fn (BookingRoom $l) => $this->formatBookingLine($l))->all(),
            'is_today'     => $date === $today,
        ];
    }

    private function formatBookingLine(BookingRoom $line): array
    {
        $booking = $line->booking;

        return [
            'booking_id'    => $booking->id,
            'tracking_code' => $booking->tracking_code,
            'guest_name'    => $booking->bookerName(),
            'guest_mobile'  => $booking->bookerMobile(),
            'check_in'      => $booking->check_in->format('Y-m-d'),
            'check_out'     => $booking->check_out->format('Y-m-d'),
            'guests'        => $line->guests,
            'room_rate'     => $line->roomRate?->name,
            'status'        => $booking->status,
            'status_label'  => $booking->statusLabel(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'available'       => 'آزاد',
            'occupied'        => 'مهمان فعلی',
            'blocked'         => 'مسدود',
            'capacity_closed' => 'بسته (سیاست قیمتی)',
            default           => $status,
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'available'       => 'success',
            'occupied'        => 'primary',
            'blocked'         => 'danger',
            'capacity_closed' => 'warning',
            default           => 'secondary',
        };
    }
}
