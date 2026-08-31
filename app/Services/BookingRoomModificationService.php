<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingGuestDetail;
use App\Models\BookingRoom;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingRoomModificationService
{
    public function __construct(
        private readonly BookingPricingService $pricing,
        private readonly ManualBookingService $manualBooking,
        private readonly RoomAvailabilityService $roomAvailability,
        private readonly PlatformCommissionService $commission,
    ) {}

    /**
     * @param  array<string, mixed>  $lineData
     */
    public function addRoomLine(Booking $booking, array $lineData, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($booking, $lineData, $actor) {
            $booking = $booking->fresh(['accommodation', 'bookingRooms.roomType', 'bookingRooms.roomRate', 'program']);

            $this->assertModifiable($booking);

            if ($booking->booking_source === 'online') {
                throw new \RuntimeException('افزودن اتاق به رزرو آنلاین از این بخش پشتیبانی نمی‌شود.');
            }

            $this->ensureBookingRoomLines($booking);
            $booking->refresh()->load(['bookingRooms.roomType', 'bookingRooms.roomRate']);

            $line = $this->resolveLineInput($booking, $lineData);
            $checkIn = $booking->check_in->format('Y-m-d');
            $checkOut = $booking->check_out->format('Y-m-d');

            $roomsNeeded = $this->pricing->roomsNeeded(
                $line['guests'],
                $line['extra_guests'],
                $line['room_type'],
                $line['children_under_6'],
                $booking->accommodation,
            );

            $this->assertRoomTypeAvailability($booking, $line['room_type'], $roomsNeeded, $checkIn, $checkOut);

            if (!empty($line['room_id'])) {
                $this->assertPhysicalRoomAvailability($booking, $line['room_id'], $checkIn, $checkOut);
            }

            $this->assertMedicalCompanionLimit(
                $booking,
                (int) $booking->guests + (int) $line['guests'],
                (int) $booking->extra_guests + (int) $line['extra_guests'],
            );

            $sortOrder = (int) ($booking->bookingRooms()->max('sort_order') ?? -1) + 1;
            $roomsConsumed = !empty($line['room_id'])
                ? 1
                : $roomsNeeded;

            $bookingRoom = BookingRoom::create([
                'booking_id'       => $booking->id,
                'room_type_id'     => $line['room_type']->id,
                'room_rate_id'     => $line['room_rate']?->id,
                'room_id'          => $line['room_id'],
                'adults'           => $line['adults'],
                'children_under_6' => $line['children_under_6'],
                'guests'           => $line['guests'],
                'extra_guests'     => $line['extra_guests'],
                'bill_full_rooms'  => $line['bill_full_rooms'],
                'rooms_consumed'   => $roomsConsumed,
                'sort_order'       => $sortOrder,
            ]);

            $this->syncBookingOccupancyTotals($booking);
            $this->createGuestSlotsForNewLine($booking, $bookingRoom, $line);

            if (!$booking->isProgram()) {
                $this->manualBooking->recalculateTotals($booking->fresh());
                $this->commission->syncBookingCommissions($booking->fresh(), $actor);
            } elseif ($booking->program) {
                $booking->program->update([
                    'guest_count'    => $booking->fresh()->guests,
                    'rooms_allocated'=> $booking->fresh()->bookingRooms()->count(),
                ]);
            }

            return $booking->fresh([
                'accommodation.city.province',
                'bookingRooms.room',
                'bookingRooms.roomType',
                'bookingRooms.roomRate',
                'guestDetails.bookingRoom.room',
                'guestDetails.bookingRoom.roomType',
                'services',
                'program',
            ]);
        });
    }

    public function addGuestToRoom(Booking $booking, int $bookingRoomId, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($booking, $bookingRoomId, $actor) {
            $booking = $booking->fresh(['accommodation', 'bookingRooms.roomType', 'bookingRooms.roomRate', 'program']);

            $this->assertModifiable($booking);

            if ($booking->booking_source === 'online') {
                throw new \RuntimeException('افزودن مهمان به رزرو آنلاین از این بخش پشتیبانی نمی‌شود.');
            }

            $this->ensureBookingRoomLines($booking);
            $booking->refresh()->load(['bookingRooms.roomType', 'guestDetails']);

            $line = $booking->bookingRooms->firstWhere('id', $bookingRoomId)
                ?? BookingRoom::query()
                    ->where('booking_id', $booking->id)
                    ->whereKey($bookingRoomId)
                    ->with('roomType')
                    ->first();

            if (!$line || !$line->roomType) {
                throw new \RuntimeException('اتاق انتخاب‌شده معتبر نیست.');
            }

            $roomType = $line->roomType;
            $capacity = max(1, (int) $roomType->capacity);
            $extraCapacity = max(0, (int) ($roomType->extra_capacity ?? 0));
            $bedGuests = max(0, (int) $line->guests);
            $floorGuests = max(0, (int) $line->extra_guests);

            if ($line->bill_full_rooms) {
                if ($bedGuests + $floorGuests >= $capacity + $extraCapacity) {
                    throw new \RuntimeException('ظرفیت این اتاق تکمیل شده است.');
                }
            } elseif ($bedGuests >= $capacity) {
                if ($extraCapacity <= 0 || $floorGuests >= $extraCapacity) {
                    throw new \RuntimeException('ظرفیت این اتاق (شامل کف‌خواب) تکمیل شده است.');
                }
            }

            $asExtraGuest = $line->bill_full_rooms
                ? ($bedGuests >= $capacity && $extraCapacity > 0)
                : ($bedGuests >= $capacity);

            $this->assertMedicalCompanionLimit(
                $booking,
                (int) $booking->guests + ($asExtraGuest ? 0 : 1),
                (int) $booking->extra_guests + ($asExtraGuest ? 1 : 0),
            );

            if ($asExtraGuest) {
                $line->update(['extra_guests' => $floorGuests + 1]);
                $booking->increment('extra_guests');
            } else {
                $line->update([
                    'adults' => (int) $line->adults + 1,
                    'guests' => $bedGuests + 1,
                ]);
                $booking->increment('guests');
            }

            $booking->refresh()->load(['bookingRooms.roomType', 'guestDetails']);

            $nextSortOrder = $asExtraGuest
                ? ((int) ($booking->guestDetails->max('sort_order') ?? -1) + 1)
                : ($this->totalBillingGuests($booking) - 1);

            BookingGuestDetail::create([
                'booking_id'      => $booking->id,
                'booking_room_id' => $line->id,
                'sort_order'      => $nextSortOrder,
                'full_name'       => 'مهمان ' . ($nextSortOrder + 1),
            ]);

            if (!$booking->isProgram()) {
                $this->manualBooking->recalculateTotals($booking->fresh());
                $this->commission->syncBookingCommissions($booking->fresh(), $actor);
            } elseif ($booking->program) {
                $booking->program->update(['guest_count' => $booking->fresh()->guests]);
            }

            return $booking->fresh([
                'accommodation.city.province',
                'bookingRooms.room',
                'bookingRooms.roomType',
                'bookingRooms.roomRate',
                'guestDetails.bookingRoom.room',
                'guestDetails.bookingRoom.roomType',
                'services',
                'program',
            ]);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function roomLinesForGuestAddition(Booking $booking): array
    {
        $booking->loadMissing(['bookingRooms.roomType', 'bookingRooms.room']);

        return $booking->bookingRooms->map(function (BookingRoom $line) {
            $roomType = $line->roomType;
            $capacity = max(1, (int) ($roomType?->capacity ?? 1));
            $extraCapacity = max(0, (int) ($roomType?->extra_capacity ?? 0));
            $bedGuests = max(0, (int) $line->guests);
            $floorGuests = max(0, (int) $line->extra_guests);
            $maxHeadcount = $line->bill_full_rooms
                ? $capacity + $extraCapacity
                : $capacity + ($extraCapacity > 0 ? $extraCapacity : 0);
            $currentHeadcount = $bedGuests + $floorGuests;
            $canAdd = $currentHeadcount < $maxHeadcount;

            return [
                'id'               => $line->id,
                'label'            => $line->physicalRoomDisplayLabel(),
                'guests'           => $currentHeadcount,
                'capacity'         => $maxHeadcount,
                'can_add_guest'    => $canAdd,
            ];
        })->values()->all();
    }

    private function assertModifiable(Booking $booking): void
    {
        if (!in_array($booking->status, ['pending', 'confirmed'], true)) {
            throw new \RuntimeException('فقط رزروهای فعال قابل ویرایش هستند.');
        }

        if ($booking->hasPendingCancellationRequest()) {
            throw new \RuntimeException('تا زمان بررسی درخواست کنسلی امکان تغییر رزرو وجود ندارد.');
        }
    }

    private function ensureBookingRoomLines(Booking $booking): void
    {
        if ($booking->bookingRooms()->exists()) {
            return;
        }

        if (!$booking->room_type_id) {
            throw new \RuntimeException('برای این رزرو خط اتاق ثبت نشده و امکان تبدیل خودکار وجود ندارد.');
        }

        $bedGuests = max(1, (int) $booking->guests - (int) ($booking->extra_guests ?? 0));
        $children = max(0, (int) ($booking->children_under_6 ?? 0));
        $adults = max(1, $bedGuests - $children);

        $line = BookingRoom::create([
            'booking_id'       => $booking->id,
            'room_type_id'     => $booking->room_type_id,
            'room_rate_id'     => $booking->room_rate_id,
            'adults'           => $adults,
            'children_under_6' => $children,
            'guests'           => $bedGuests,
            'extra_guests'     => (int) ($booking->extra_guests ?? 0),
            'bill_full_rooms'  => (bool) $booking->bill_full_rooms,
            'rooms_consumed'   => max(1, (int) ($booking->rooms_consumed ?? 1)),
            'sort_order'       => 0,
        ]);

        $booking->guestDetails()->whereNull('booking_room_id')->update([
            'booking_room_id' => $line->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $lineData
     * @return array{
     *   room_type: RoomType,
     *   room_rate: ?RoomRate,
     *   room_id: ?int,
     *   adults: int,
     *   children_under_6: int,
     *   guests: int,
     *   extra_guests: int,
     *   bill_full_rooms: bool
     * }
     */
    private function resolveLineInput(Booking $booking, array $lineData): array
    {
        $roomTypeId = (int) ($lineData['room_type_id'] ?? 0);
        $roomRateId = (int) ($lineData['room_rate_id'] ?? 0);
        $roomId = !empty($lineData['room_id']) ? (int) $lineData['room_id'] : null;

        $roomType = RoomType::query()
            ->where('accommodation_id', $booking->accommodation_id)
            ->where('is_active', true)
            ->find($roomTypeId);

        if (!$roomType) {
            throw new \RuntimeException('نوع اتاق انتخاب‌شده معتبر نیست.');
        }

        $roomRate = null;
        if ($roomRateId > 0) {
            $roomRate = RoomRate::query()->find($roomRateId);
            if (!$roomRate || (int) $roomRate->room_type_id !== (int) $roomType->id) {
                throw new \RuntimeException('تعرفه انتخاب‌شده معتبر نیست.');
            }
        } else {
            $roomRate = $roomType->rates()->where('is_active', true)->orderBy('price_per_night')->first();
        }

        if ($roomId) {
            $room = Room::find($roomId);
            if (!$room || (int) $room->room_type_id !== (int) $roomType->id) {
                throw new \RuntimeException('اتاق فیزیکی انتخاب‌شده معتبر نیست.');
            }
        }

        $childrenUnder6 = max(0, (int) ($lineData['children_under_6'] ?? 0));
        $adults = max(1, (int) ($lineData['adults'] ?? 1));
        $guests = max(1, (int) ($lineData['guests'] ?? ($adults + $childrenUnder6)));
        $extraGuests = max(0, (int) ($lineData['extra_guests'] ?? 0));
        $billFullRooms = (bool) ($lineData['bill_full_rooms'] ?? false);

        if ($childrenUnder6 >= $guests) {
            throw new \RuntimeException('تعداد کودک زیر ۶ سال باید کمتر از تعداد نفرات اتاق باشد.');
        }

        $capacity = max(1, (int) $roomType->capacity);
        $extraCapacity = max(0, (int) ($roomType->extra_capacity ?? 0));

        if (!$billFullRooms && $guests > $capacity) {
            if ($extraGuests <= 0 || $guests - $extraGuests > $capacity || $extraGuests > $extraCapacity) {
                throw new \RuntimeException('تعداد نفرات از ظرفیت اتاق بیشتر است.');
            }
        }

        if ($extraGuests > 0 && $extraCapacity <= 0) {
            throw new \RuntimeException('این نوع اتاق ظرفیت کف‌خواب ندارد.');
        }

        return [
            'room_type'        => $roomType,
            'room_rate'        => $roomRate,
            'room_id'          => $roomId,
            'adults'           => $adults,
            'children_under_6' => $childrenUnder6,
            'guests'           => $guests,
            'extra_guests'     => $extraGuests,
            'bill_full_rooms'  => $billFullRooms,
        ];
    }

    private function assertRoomTypeAvailability(
        Booking $booking,
        RoomType $roomType,
        int $roomsNeeded,
        string $checkIn,
        string $checkOut,
    ): void {
        $map = $roomType->availabilityMap($checkIn, $checkOut);

        foreach ($map as $day) {
            if (!empty($day['is_blocked'])) {
                throw new \RuntimeException('بازه رزرو برای «' . $roomType->name . '» مسدود است.');
            }

            if ((int) ($day['available_rooms'] ?? 0) < $roomsNeeded) {
                throw new \RuntimeException('ظرفیت کافی برای افزودن اتاق «' . $roomType->name . '» در این بازه وجود ندارد.');
            }
        }
    }

    private function assertPhysicalRoomAvailability(
        Booking $booking,
        int $roomId,
        string $checkIn,
        string $checkOut,
    ): void {
        $room = Room::with('roomType')->find($roomId);

        if (!$room || $room->roomType?->accommodation_id !== $booking->accommodation_id) {
            throw new \RuntimeException('اتاق فیزیکی انتخاب‌شده معتبر نیست.');
        }

        $assignedRoomIds = $booking->bookingRooms()
            ->pluck('room_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (!$this->roomAvailability->isRoomAvailable(
            $room,
            $checkIn,
            $checkOut,
            $assignedRoomIds,
            $booking->id,
        )) {
            throw new \RuntimeException('اتاق «' . $room->name . '» در بازه رزرو در دسترس نیست.');
        }
    }

    /**
     * @param  array{
     *   room_type: RoomType,
     *   room_rate: ?RoomRate,
     *   room_id: ?int,
     *   adults: int,
     *   children_under_6: int,
     *   guests: int,
     *   extra_guests: int,
     *   bill_full_rooms: bool
     * }  $line
     */
    private function createGuestSlotsForNewLine(Booking $booking, BookingRoom $bookingRoom, array $line): void
    {
        $previousLines = $booking->bookingRooms()
            ->where('id', '!=', $bookingRoom->id)
            ->with('roomType')
            ->orderBy('sort_order')
            ->get();

        $previousBilling = $previousLines->isEmpty()
            ? 0
            : $this->pricing->totalBillingGuestsForRoomLines(
                $previousLines->map(fn (BookingRoom $row) => [
                    'room_type'        => $row->roomType,
                    'guests'           => $row->guests,
                    'children_under_6' => $row->children_under_6,
                    'extra_guests'     => $row->extra_guests,
                    'bill_full_rooms'  => $row->bill_full_rooms,
                ])->all(),
                $booking->accommodation,
            );

        $newLineBilling = $this->pricing->totalBillingGuestsForRoomLines([
            [
                'room_type'        => $line['room_type'],
                'guests'           => $line['guests'],
                'children_under_6' => $line['children_under_6'],
                'extra_guests'     => $line['extra_guests'],
                'bill_full_rooms'  => $line['bill_full_rooms'],
            ],
        ], $booking->accommodation);

        for ($sortOrder = $previousBilling; $sortOrder < $previousBilling + $newLineBilling; $sortOrder++) {
            if ($booking->guestDetails()->where('sort_order', $sortOrder)->exists()) {
                continue;
            }

            BookingGuestDetail::create([
                'booking_id'      => $booking->id,
                'booking_room_id' => $bookingRoom->id,
                'sort_order'      => $sortOrder,
                'full_name'       => 'مهمان ' . ($sortOrder + 1),
            ]);
        }
    }

    private function syncBookingOccupancyTotals(Booking $booking): void
    {
        /** @var Collection<int, BookingRoom> $lines */
        $lines = $booking->bookingRooms()->get();

        $booking->update([
            'room_type_id'     => $lines->first()?->room_type_id ?? $booking->room_type_id,
            'room_rate_id'     => $lines->first()?->room_rate_id ?? $booking->room_rate_id,
            'guests'           => (int) $lines->sum('guests'),
            'children_under_6' => (int) $lines->sum('children_under_6'),
            'extra_guests'     => (int) $lines->sum('extra_guests'),
            'rooms_consumed'   => (int) $lines->sum('rooms_consumed'),
            'bill_full_rooms'  => $lines->contains(fn (BookingRoom $line) => $line->bill_full_rooms),
        ]);
    }

    private function totalBillingGuests(Booking $booking): int
    {
        return $this->pricing->totalBillingGuestsForRoomLines(
            $booking->bookingRooms->map(fn (BookingRoom $line) => [
                'room_type'        => $line->roomType,
                'guests'           => $line->guests,
                'children_under_6' => $line->children_under_6,
                'extra_guests'     => $line->extra_guests,
                'bill_full_rooms'  => $line->bill_full_rooms,
            ])->all(),
            $booking->accommodation,
        );
    }

    private function assertMedicalCompanionLimit(Booking $booking, int $guests, int $extraGuests): void
    {
        if (!$booking->isMedicalAccommodation()) {
            return;
        }

        app(MedicalAccommodationBillingService::class)
            ->assertCompanionLimit($booking, $guests, $extraGuests);
    }
}
