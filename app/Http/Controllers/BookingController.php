<?php

namespace App\Http\Controllers;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Review;
use App\Services\GuestBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function create(Request $request, Accommodation $accommodation)
    {
        $checkIn  = $request->input('check_in');
        $checkOut = $request->input('check_out');
        $guests   = $request->input('guests', 1);

        return view('bookings.create', compact('accommodation', 'checkIn', 'checkOut', 'guests'));
    }

    public function store(Request $request, Accommodation $accommodation, GuestBookingService $service)
    {
        $request->validate([
            'check_in'      => ['required', 'date', 'after_or_equal:today'],
            'check_out'     => ['required', 'date', 'after:check_in'],
            'guests'        => ['required', 'integer', 'min:1', 'max:' . $accommodation->capacity],
            'room_type_id'  => ['nullable', 'integer', 'exists:room_types,id'],
            'room_rate_id'  => ['nullable', 'integer', 'exists:room_rates,id'],
            'extra_guests'  => ['nullable', 'integer', 'min:0', 'max:10'],
            'children_under_6' => ['nullable', 'integer', 'min:0', 'max:10'],
            'bill_full_rooms' => ['nullable', 'boolean'],
        ], [
            'check_in.required'        => 'تاریخ ورود الزامی است.',
            'check_in.after_or_equal'  => 'تاریخ ورود نمی‌تواند در گذشته باشد.',
            'check_out.required'       => 'تاریخ خروج الزامی است.',
            'check_out.after'          => 'تاریخ خروج باید بعد از تاریخ ورود باشد.',
            'guests.max'               => "حداکثر ظرفیت این اقامتگاه {$accommodation->capacity} نفر است.",
        ]);

        $booking = $service->store($accommodation, Auth::user(), $request->all());

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

    /**
     * @deprecated Instant self-cancel has been replaced by the cancellation/refund request
     * workflow (requires admin/host approval). Kept only to redirect old links/bookmarks.
     */
    public function cancel(Booking $booking)
    {
        abort_if($booking->user_id !== Auth::id(), 403);

        return redirect()->route('bookings.show', ['booking' => $booking, 'cancel' => 1])
            ->with('status', 'برای لغو رزرو، فرم درخواست کنسلی باز شد.');
    }
}
