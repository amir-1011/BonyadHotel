<?php

namespace App\Http\Controllers;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Review;
use App\Models\RoomRate;
use App\Models\RoomType;
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
            if (!$roomType->isAvailable($checkIn, $checkOut, 1)) {
                return back()->withErrors(['check_in' => 'متأسفانه این نوع اتاق در بازه تاریخ انتخابی شما در دسترس نیست.']);
            }
        } else {
            if (!$accommodation->isAvailable($checkIn, $checkOut)) {
                return back()->withErrors(['check_in' => 'متأسفانه این اقامتگاه در بازه تاریخ انتخابی شما رزرو شده است.']);
            }
        }

        $pricePerNight = $roomRate ? $roomRate->price_per_night : $accommodation->price_per_night;

        $user              = Auth::user();
        $nights            = (int) (new \DateTime($checkIn))->diff(new \DateTime($checkOut))->days;
        $basePrice         = $pricePerNight * $nights;
        $discountPct       = $user->discount_percentage;
        $discountAmount    = (int) ($basePrice * $discountPct / 100);
        $totalPrice        = $basePrice - $discountAmount;

        $booking = Booking::create([
            'user_id'             => $user->id,
            'accommodation_id'    => $accommodation->id,
            'room_type_id'        => $roomType?->id,
            'room_rate_id'        => $roomRate?->id,
            'check_in'            => $checkIn,
            'check_out'           => $checkOut,
            'guests'              => $request->input('guests'),
            'nights'              => $nights,
            'base_price'          => $basePrice,
            'discount_percentage' => $discountPct,
            'discount_amount'     => $discountAmount,
            'total_price'         => $totalPrice,
            'status'              => 'confirmed',
            'tracking_code'       => strtoupper(Str::random(10)),
        ]);

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
