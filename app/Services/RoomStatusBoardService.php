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
                'summary'            => $this->summarizeRooms($allRooms),
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
            $status = $this->isProgramBookingLine($current) ? 'program_occupied' : 'occupied';
        } elseif ($capacityClosed) {
            $status = 'capacity_closed';
        } else {
            $status = 'available';
        }

        $hasFutureProgram = $future->isNotEmpty()
            && $this->isProgramBookingLine($future->first())
            && $status === 'available';

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
            'has_future_program' => $hasFutureProgram,
            'block_reason' => $isRoomBlocked ? ($blockReason ?: 'مسدود توسط کاربر') : null,
            'current_booking' => $current ? $this->formatBookingLine($current) : null,
            'future_bookings' => $future->map(fn (BookingRoom $l) => $this->formatBookingLine($l))->all(),
            'is_today'     => $date === $today,
        ];
    }

    /**
     * Numeric snapshot of one accommodation's rooms for the selected day.
     *
     * @param  array<int, array<string, mixed>>  $rooms
     * @return array{
     *     total: int,
     *     available: int,
     *     occupied: int,
     *     occupied_guests: int,
     *     program: int,
     *     program_guests: int,
     *     future: int,
     *     future_program: int,
     *     capacity_closed: int,
     *     blocked: int
     * }
     */
    public function summarizeRooms(array $rooms): array
    {
        $summary = [
            'total'            => count($rooms),
            'available'        => 0,
            'occupied'         => 0,
            'occupied_guests'  => 0,
            'program'          => 0,
            'program_guests'   => 0,
            'future'           => 0,
            'future_program'   => 0,
            'capacity_closed'  => 0,
            'blocked'          => 0,
        ];

        foreach ($rooms as $room) {
            $status = (string) ($room['status'] ?? '');
            $currentGuests = (int) ($room['current_booking']['guests'] ?? 0);

            if ($status === 'available') {
                $summary['available']++;
            } elseif ($status === 'occupied') {
                $summary['occupied']++;
                $summary['occupied_guests'] += $currentGuests;
            } elseif ($status === 'program_occupied') {
                $summary['program']++;
                $summary['program_guests'] += $currentGuests;
            } elseif ($status === 'capacity_closed') {
                $summary['capacity_closed']++;
            } elseif ($status === 'blocked') {
                $summary['blocked']++;
            }

            $future = $room['future_bookings'] ?? [];
            if (is_array($future) && $future !== []) {
                $summary['future']++;
                $first = $future[0] ?? [];
                if (!empty($room['has_future_program']) || !empty($first['is_program'])) {
                    $summary['future_program']++;
                }
            }
        }

        return $summary;
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
            'booking_source'=> $booking->booking_source,
            'is_program'    => $booking->booking_source === 'program',
        ];
    }

    private function isProgramBookingLine(BookingRoom $line): bool
    {
        return $line->booking?->booking_source === 'program';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'available'         => 'آزاد',
            'occupied'          => 'مهمان فعلی',
            'program_occupied'  => 'اردو / برنامه',
            'blocked'           => 'مسدود',
            'capacity_closed'   => 'بسته (سیاست قیمتی)',
            default             => $status,
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'available'         => 'success',
            'occupied'          => 'primary',
            'program_occupied'  => 'purple',
            'blocked'           => 'danger',
            'capacity_closed'   => 'warning',
            default             => 'secondary',
        };
    }
}
