<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestBookingService
{
    public function __construct(
        private readonly BookingPricingService $pricing,
        private readonly PlatformCommissionService $commission,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(Accommodation $accommodation, User $user, array $data): Booking
    {
        $checkIn  = $data['check_in'];
        $checkOut = $data['check_out'];
        $guests   = (int) $data['guests'];
        $childrenUnder6 = max(0, (int) ($data['children_under_6'] ?? 0));

        if ($childrenUnder6 >= $guests) {
            throw ValidationException::withMessages([
                'guests' => 'تعداد کودک زیر ۶ سال باید کمتر از تعداد کل مهمانان باشد.',
            ]);
        }

        $roomType = null;
        $roomRate = null;

        if (!empty($data['room_rate_id'])) {
            $roomRate = RoomRate::find($data['room_rate_id']);
            if ($roomRate) {
                $roomType = $roomRate->roomType;
                if ($roomType->accommodation_id !== $accommodation->id) {
                    $roomRate = null;
                    $roomType = null;
                }
            }
        } elseif (!empty($data['room_type_id'])) {
            $roomType = RoomType::find($data['room_type_id']);
            if ($roomType && $roomType->accommodation_id !== $accommodation->id) {
                $roomType = null;
            }
        }

        $extraGuests   = (int) ($data['extra_guests'] ?? 0);
        $billFullRooms = (bool) ($data['bill_full_rooms'] ?? false);

        if ($roomType) {
            if ($extraGuests > 0) {
                $maxExtra = (int) ($roomType->extra_capacity ?? 0);
                if ($maxExtra <= 0 || $extraGuests > $maxExtra) {
                    throw ValidationException::withMessages([
                        'check_in' => 'تعداد نفرات اضافه وارد شده بیش از ظرفیت کف‌خوابی این اتاق است.',
                    ]);
                }
            }

            if ($billFullRooms) {
                if ($extraGuests > 0) {
                    throw ValidationException::withMessages([
                        'check_in' => 'رزرو کامل اتاق با کف‌خوابی همزمان امکان‌پذیر نیست.',
                    ]);
                }
                $capacity = max(1, (int) $roomType->capacity);
                if ($guests >= $capacity && $guests % $capacity === 0) {
                    throw ValidationException::withMessages([
                        'check_in' => 'برای تعداد نفرات انتخابی نیازی به رزرو کامل اتاق نیست.',
                    ]);
                }
            }
        } else {
            $extraGuests   = 0;
            $billFullRooms = false;
        }

        $pricing = $this->pricing->calculate([
            'check_in'                 => $checkIn,
            'check_out'                => $checkOut,
            'guests'                   => $guests,
            'children_under_6'         => $childrenUnder6,
            'extra_guests'             => $extraGuests,
            'bill_full_rooms'          => $billFullRooms,
            'accommodation'            => $accommodation,
            'room_type'                => $roomType,
            'room_rate'                => $roomRate,
            'veteran_type'             => $user->normalizedVeteranType(),
            'secondary_veteran_type'   => $user->normalizedSecondaryVeteranType(),
            'user_id'                  => $user->id,
            'national_id'              => $user->national_id,
        ]);

        $roomsNeeded = (int) $pricing['rooms_needed'];

        if ($roomType) {
            if (!$roomType->isAvailable($checkIn, $checkOut, $roomsNeeded)) {
                throw ValidationException::withMessages([
                    'check_in' => 'متأسفانه ظرفیت کافی برای تعداد نفرات انتخابی در بازه تاریخ انتخابی شما وجود ندارد.',
                ]);
            }
        } elseif (!$accommodation->isAvailable($checkIn, $checkOut)) {
            throw ValidationException::withMessages([
                'check_in' => 'متأسفانه این اقامتگاه در بازه تاریخ انتخابی شما رزرو شده است.',
            ]);
        }

        $booking = Booking::create([
            'user_id'             => $user->id,
            'accommodation_id'    => $accommodation->id,
            'room_type_id'        => $roomType?->id,
            'room_rate_id'        => $roomRate?->id,
            'check_in'            => $checkIn,
            'check_out'           => $checkOut,
            'guests'              => $guests,
            'children_under_6'    => $pricing['children_under_6'],
            'rooms_consumed'      => $roomsNeeded,
            'extra_guests'        => $extraGuests,
            'extra_guests_price'  => $pricing['extra_guests_total'],
            'bill_full_rooms'     => $billFullRooms,
            'nights'              => $pricing['nights'],
            'base_price'          => $pricing['subtotal_before_discount'],
            'discount_percentage' => $pricing['accommodation_discount_percentage'],
            'discount_amount'     => $pricing['discount_amount'],
            'total_price'         => $pricing['total_price'],
            'status'              => 'confirmed',
            'booking_source'      => 'online',
            'tracking_code'       => strtoupper(Str::random(10)),
        ]);

        $booking->load('accommodation');
        $this->commission->syncBookingCommissions($booking, $user);

        return $booking;
    }
}
