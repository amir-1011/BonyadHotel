<?php

namespace App\Http\Controllers;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request, Accommodation $accommodation)
    {
        $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        // Only guests who have a confirmed, past booking can review
        $hasBooking = Booking::where('user_id', Auth::id())
            ->where('accommodation_id', $accommodation->id)
            ->where('status', 'confirmed')
            ->where('check_out', '<', now()->toDateString())
            ->exists();

        if (!$hasBooking) {
            return back()->withErrors(['rating' => 'فقط مهمانانی که اقامت خود را تکمیل کرده‌اند می‌توانند نظر ثبت کنند.']);
        }

        // One review per accommodation per user
        Review::updateOrCreate(
            ['user_id' => Auth::id(), 'accommodation_id' => $accommodation->id],
            [
                'rating'     => $request->rating,
                'comment'    => $request->comment,
                'is_visible' => true,
                'booking_id' => $request->input('booking_id'),
            ]
        );

        // Redirect back to booking detail if coming from there
        if ($request->filled('booking_id')) {
            return redirect()->route('bookings.show', $request->booking_id)
                ->with('status', 'نظر شما با موفقیت ثبت شد.');
        }

        return back()->with('status', 'نظر شما با موفقیت ثبت شد.');
    }
}
