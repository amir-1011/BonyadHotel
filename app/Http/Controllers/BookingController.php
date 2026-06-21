<?php

namespace App\Http\Controllers;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Review;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Services\PlatformCommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function create(Request $request, Accommodation $accommodation)
    {
        $checkIn  = $request->input('check_in');
        $checkOut = $request->input('check_out');
        $guests   = $request->input('guests', 1);

        return view('bookings.create', compact('accommodation', 'checkIn', 'checkOut', 'guests'));
    }

    public function store(Request $request, Accommodation $accommodation)
    {
        $request->validate([
            'check_in'      => ['required', 'date', 'after_or_equal:today'],
            'check_out'     => ['required', 'date', 'after:check_in'],
            'guests'        => ['required', 'integer', 'min:1', 'max:' . $accommodation->capacity],
            'room_type_id'  => ['nullable', 'integer', 'exists:room_types,id'],
            'room_rate_id'  => ['nullable', 'integer', 'exists:room_rates,id'],
            'extra_guests'  => ['nullable', 'integer', 'min:0', 'max:10'],
            'bill_full_rooms' => ['nullable', 'boolean'],
        ], [
            'check_in.required'        => 'تاریخ ورود الزامی است.',
            'check_in.after_or_equal'  => 'تاریخ ورود نمی‌تواند در گذشته باشد.',
            'check_out.required'       => 'تاریخ خروج الزامی است.',
            'check_out.after'          => 'تاریخ خروج باید بعد از تاریخ ورود باشد.',
            'guests.max'               => "حداکثر ظرفیت این اقامتگاه {$accommodation->capacity} نفر است.",
        ]);

        $checkIn  = $request->input('check_in');
        $checkOut = $request->input('check_out');

        // Determine room type / rate early so we can do the right availability check
        $roomTypeId = $request->input('room_type_id');
        $roomRateId = $request->input('room_rate_id');
        $roomRate   = null;
        $roomType   = null;

        if ($roomRateId) {
            $roomRate = RoomRate::find($roomRateId);
            if ($roomRate) {
                $roomType = $roomRate->roomType;
                // Security: ensure rate belongs to this accommodation
                if ($roomType->accommodation_id !== $accommodation->id) {
                    $roomRate = null;
                    $roomType = null;
                    $roomRateId = null;
                    $roomTypeId = null;
                }
            }
        } elseif ($roomTypeId) {
            $roomType = RoomType::find($roomTypeId);
            if ($roomType && $roomType->accommodation_id !== $accommodation->id) {
                $roomType = null;
                $roomTypeId = null;
            }
        }

        // Check availability: prefer room-type-level check, fall back to accommodation-level
        if ($roomType) {
            // Policy: number of rooms needed = ceil(guests / room_type.capacity)
            // But if extra_guests is provided and within allowed extra_capacity, reduce rooms by 1
            $guests      = (int) $request->input('guests', 1);
            $extraGuests = (int) $request->input('extra_guests', 0);
            $billFullRooms = $request->boolean('bill_full_rooms');

            // Validate extra_guests against room type's extra_capacity
            if ($extraGuests > 0) {
                $maxExtra = (int) ($roomType->extra_capacity ?? 0);
                if ($maxExtra <= 0 || $extraGuests > $maxExtra) {
                    return back()->withErrors(['check_in' => 'تعداد نفرات اضافه وارد شده بیش از ظرفیت کف‌خوابی این اتاق است.']);
                }
                // With extra guests, the "standard" guests reduce by extra_guests for room calculation
                $standardGuests = $guests - $extraGuests;
                $roomsNeeded    = max(1, (int) ceil($standardGuests / max(1, (int) $roomType->capacity)));
            } else {
                $roomsNeeded = (int) ceil($guests / max(1, (int) $roomType->capacity));
            }

            if ($billFullRooms) {
                if ($extraGuests > 0) {
                    return back()->withErrors(['check_in' => 'رزرو کامل اتاق با کف‌خوابی همزمان امکان‌پذیر نیست.']);
                }
                $capacity = max(1, (int) $roomType->capacity);
                if ($guests >= $capacity && $guests % $capacity === 0) {
                    return back()->withErrors(['check_in' => 'برای تعداد نفرات انتخابی نیازی به رزرو کامل اتاق نیست.']);
                }
            }

            if (!$roomType->isAvailable($checkIn, $checkOut, $roomsNeeded)) {
                return back()->withErrors(['check_in' => 'متأسفانه ظرفیت کافی برای تعداد نفرات انتخابی در بازه تاریخ انتخابی شما وجود ندارد.']);
            }
        } else {
            $guests        = (int) $request->input('guests', 1);
            $extraGuests   = 0;
            $billFullRooms = false;
            $roomsNeeded   = 1;
            if (!$accommodation->isAvailable($checkIn, $checkOut)) {
                return back()->withErrors(['check_in' => 'متأسفانه این اقامتگاه در بازه تاریخ انتخابی شما رزرو شده است.']);
            }
        }

        $pricePerNight = $roomRate ? $roomRate->price_per_night : $accommodation->price_per_night;

        $user   = Auth::user();
        $nights = (int) (new \DateTime($checkIn))->diff(new \DateTime($checkOut))->days;

        // Build per-night base price by accounting for host's daily price overrides.
        // availabilityMap returns effective_price (after host's custom_price + host's discount_percentage).
        // We use this as the "base" per night, then apply the user's veteran/special discount on top.
        $availMap  = $roomType ? $roomType->availabilityMap($checkIn, $checkOut) : [];
        $basePrice = 0;
        // Extra guests price: extra_guests × extra_capacity_price × nights (fixed price, no per-night override)
        $extraGuestsTotal = 0;
        if ($extraGuests > 0 && $roomType && $roomType->extra_capacity_price) {
            $extraGuestsTotal = $extraGuests * (int) $roomType->extra_capacity_price * $nights;
        }
        $cursor  = new \DateTime($checkIn);
        $endDate = new \DateTime($checkOut);
        // Charge per-person rate: full-room billing uses all beds in reserved rooms
        if ($billFullRooms && $roomType) {
            $billingGuests = $roomsNeeded * max(1, (int) $roomType->capacity);
        } else {
            $billingGuests = $guests - $extraGuests;
        }
        while ($cursor < $endDate) {
            $dayKey     = $cursor->format('Y-m-d');
            $dayData    = $availMap[$dayKey] ?? null;
            // effective_price is already post-host-discount; fall back to rate price
            $nightPrice = ($dayData && isset($dayData['effective_price']) && $dayData['effective_price'] !== null)
                ? (int) $dayData['effective_price']
                : $pricePerNight;
            // Multiply by billable guests (price is per-person per-night)
            $basePrice += $nightPrice * $billingGuests;
            $cursor->modify('+1 day');
        }
        $basePrice += $extraGuestsTotal;

        // Apply user's veteran / special-group discount on top
        $discountPct     = $user->discount_percentage;
        $discountAmount  = (int) round($basePrice * $discountPct / 100);
        $totalPrice      = $basePrice - $discountAmount;

        $booking = Booking::create([
            'user_id'             => $user->id,
            'accommodation_id'    => $accommodation->id,
            'room_type_id'        => $roomType?->id,
            'room_rate_id'        => $roomRate?->id,
            'check_in'            => $checkIn,
            'check_out'           => $checkOut,
            'guests'              => $request->input('guests'),
            'rooms_consumed'      => $roomsNeeded,
            'extra_guests'        => $extraGuests,
            'extra_guests_price'  => $extraGuestsTotal,
            'nights'              => $nights,
            'base_price'          => $basePrice,
            'discount_percentage' => $discountPct,
            'discount_amount'     => $discountAmount,
            'total_price'         => $totalPrice,
            'status'              => 'confirmed',
            'booking_source'      => 'online',
            'tracking_code'       => strtoupper(Str::random(10)),
        ]);

        $booking->load('accommodation');
        app(PlatformCommissionService::class)->syncBookingCommissions($booking, $user);

        return redirect()->route('bookings.show', $booking)
            ->with('status', 'رزرو شما با موفقیت ثبت شد.');
    }

    public function index()
    {
        $bookings = Auth::user()->bookings()
            ->with('accommodation.city.province')
            ->latest()
            ->paginate(10);

        // IDs of accommodations the user has already reviewed (keyed for O(1) lookup)
        $reviewedAccIds = Review::where('user_id', Auth::id())
            ->pluck('accommodation_id')
            ->flip();

        return view('bookings.index', compact('bookings', 'reviewedAccIds'));
    }

    public function show(Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);
        $booking->load('accommodation.city.province', 'user');

        $canReview = $booking->status === 'confirmed'
            && $booking->check_out < now()->toDateString();

        $userReview = $canReview
            ? Review::where('user_id', Auth::id())
                ->where('accommodation_id', $booking->accommodation_id)
                ->first()
            : null;

        return view('bookings.show', compact('booking', 'canReview', 'userReview'));
    }

    public function cancel(Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);
        abort_if($booking->status !== 'confirmed', 422, 'این رزرو قابل لغو نیست.');

        $booking->update(['status' => 'cancelled']);

        return back()->with('status', 'رزرو با موفقیت لغو شد.');
    }
}
