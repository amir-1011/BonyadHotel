<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with('user', 'accommodation.city')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($w) use ($s) {
                $w->where('tracking_code', 'like', "%$s%")
                  ->orWhereHas('user', fn($q) => $q->where('name','like',"%$s%")->orWhere('mobile','like',"%$s%"))
                  ->orWhereHas('accommodation', fn($q) => $q->where('name','like',"%$s%"));
            });
        }

        $bookings = $query->paginate(25)->withQueryString();
        return view('admin.bookings.index', compact('bookings'));
    }

    public function show(Booking $booking)
    {
        $booking->load('user', 'accommodation.city.province');
        return view('admin.bookings.show', compact('booking'));
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $request->validate(['status' => ['required', 'in:pending,confirmed,cancelled']]);
        $booking->update(['status' => $request->status]);
        return back()->with('status', 'وضعیت رزرو به‌روز شد.');
    }
}
