<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use App\Support\StayDurationPicker;
use Illuminate\Support\Facades\DB;

class BookingStayExtensionService
{
    public function __construct(
        private readonly ManualBookingService $manualBooking,
        private readonly RoomAvailabilityService $roomAvailability,
    ) {}

    public function extendCheckout(Booking $booking, string $newCheckOut): Booking
    {
        return DB::transaction(function () use ($booking, $newCheckOut) {
            $booking = $booking->fresh([
                'accommodation.medicalAccommodationSetting',
                'medicalContract',
                'bookingRooms.room.roomType',
                'bookingRooms.roomType',
            ]);

            $this->assertAdjustable($booking, $newCheckOut);

            $oldCheckOut = $booking->check_out->format('Y-m-d');
            $checkIn = $booking->check_in->format('Y-m-d');

            if ($booking->isMedicalAccommodation() && $booking->medicalContract) {
                app(MedicalAccommodationBillingService::class)->assertStayWithinContract(
                    $booking->medicalContract,
                    $checkIn,
                    $newCheckOut,
                );
            }
            $isShorten = $newCheckOut < $oldCheckOut;

            if (!$isShorten) {
                $this->assertExtensionAvailability($booking, $oldCheckOut, $newCheckOut);
            }

            $booking->update([
                'check_out' => $newCheckOut,
                'nights'    => StayDurationPicker::nightsBetween($checkIn, $newCheckOut),
            ]);

            if (!$booking->isProgram()) {
                $this->manualBooking->recalculateTotals($booking->fresh());
            }

            return $booking->fresh([
                'accommodation.city.province',
                'bookingRooms.room',
                'bookingRooms.roomType',
                'bookingRooms.roomRate',
                'services',
                'guestDetails',
                'program',
                'employer',
            ]);
        });
    }

    private function assertAdjustable(Booking $booking, string $newCheckOut): void
    {
        if (!in_array($booking->status, ['pending', 'confirmed'], true)) {
            throw new \RuntimeException('فقط رزروهای فعال قابل تغییر تاریخ هستند.');
        }

        if ($booking->hasPendingCancellationRequest()) {
            throw new \RuntimeException('تا زمان بررسی درخواست کنسلی امکان تغییر تاریخ رزرو وجود ندارد.');
        }

        $oldCheckOut = $booking->check_out->format('Y-m-d');
        $checkIn = $booking->check_in->format('Y-m-d');

        if ($newCheckOut === $oldCheckOut) {
            throw new \RuntimeException('تاریخ پایان جدید با تاریخ پایان فعلی یکسان است.');
        }

        if ($newCheckOut < $oldCheckOut && !$booking->canShortenStay()) {
            throw new \RuntimeException('کاهش تاریخ اقامت بدون جریمه فقط برای اسکان درمانی مجاز است.');
        }

        if ($newCheckOut <= $checkIn) {
            throw new \RuntimeException('تاریخ پایان باید بعد از تاریخ ورود باشد.');
        }

        $nights = StayDurationPicker::nightsBetween($checkIn, $newCheckOut);

        [$valid, $message] = StayDurationPicker::validateNightsInput($nights);

        if (!$valid) {
            throw new \RuntimeException($message ?? 'مدت اقامت نامعتبر است.');
        }
    }

    private function assertExtensionAvailability(Booking $booking, string $oldCheckOut, string $newCheckOut): void
    {
        $bookingRooms = $booking->bookingRooms;
        $physicalLines = $bookingRooms->filter(fn ($line) => !empty($line->room_id));

        if ($physicalLines->isNotEmpty()) {
            $this->assertPhysicalRoomExtensionAvailability($booking, $physicalLines, $oldCheckOut, $newCheckOut);

            return;
        }

        $consumptionByRoomType = [];

        foreach ($bookingRooms as $line) {
            if (!$line->room_type_id) {
                continue;
            }

            $roomsNeeded = max(1, (int) ($line->rooms_consumed ?? 1));
            $consumptionByRoomType[(int) $line->room_type_id] = ($consumptionByRoomType[(int) $line->room_type_id] ?? 0) + $roomsNeeded;
        }

        if ($consumptionByRoomType === [] && $booking->room_type_id) {
            $consumptionByRoomType[(int) $booking->room_type_id] = max(1, (int) ($booking->rooms_consumed ?? 1));
        }

        foreach ($consumptionByRoomType as $roomTypeId => $roomsNeeded) {
            $roomType = RoomType::query()
                ->where('accommodation_id', $booking->accommodation_id)
                ->find($roomTypeId);

            if (!$roomType) {
                throw new \RuntimeException('نوع اتاق رزرو معتبر نیست.');
            }

            if (!$roomType->isAvailable($oldCheckOut, $newCheckOut, $roomsNeeded)) {
                throw new \RuntimeException('ظرفیت کافی برای شب‌های تمدید در «' . $roomType->name . '» وجود ندارد.');
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\BookingRoom>  $physicalLines
     */
    private function assertPhysicalRoomExtensionAvailability(
        Booking $booking,
        $physicalLines,
        string $oldCheckOut,
        string $newCheckOut,
    ): void {
        $assignedRoomIds = $physicalLines
            ->pluck('room_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        foreach ($physicalLines as $line) {
            $room = $line->room ?? Room::with('roomType')->find($line->room_id);

            if (!$room || $room->roomType?->accommodation_id !== $booking->accommodation_id) {
                throw new \RuntimeException('اتاق اختصاص‌داده‌شده معتبر نیست.');
            }

            $otherAssigned = array_values(array_diff($assignedRoomIds, [(int) $line->room_id]));

            if (!$this->roomAvailability->isRoomAvailable(
                $room,
                $oldCheckOut,
                $newCheckOut,
                $otherAssigned,
                $booking->id,
            )) {
                throw new \RuntimeException('اتاق «' . $room->name . '» در شب‌های تمدید در دسترس نیست.');
            }
        }
    }
}
