<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $accIds = Auth::user()->accommodations()->pluck('id');

        $query = Booking::whereIn('accommodation_id', $accIds)
            ->with('user', 'accommodation');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('accommodation_id') && $accIds->contains($request->accommodation_id)) {
            $query->where('accommodation_id', $request->accommodation_id);
        }

        $myAccommodations = Auth::user()->accommodations()->orderBy('name')->get(['id','name']);
        $bookings = $query->latest()->paginate(20)->withQueryString();
        return view('host.bookings.index', compact('bookings', 'myAccommodations'));
    }

    public function show(Booking $booking)
    {
        $accIds = Auth::user()->accommodations()->pluck('id');
        abort_if(!$accIds->contains($booking->accommodation_id), 403);
        $booking->load('user', 'accommodation.city');
        return view('host.bookings.show', compact('booking'));
    }

    public function confirm(Booking $booking)
    {
        $accIds = Auth::user()->accommodations()->pluck('id');
        abort_if(!$accIds->contains($booking->accommodation_id), 403);
        abort_if($booking->status !== 'pending', 422);
        $booking->update(['status' => 'confirmed']);
        return back()->with('status', 'رزرو تأیید شد.');
    }

    public function cancel(Booking $booking)
    {
        $accIds = Auth::user()->accommodations()->pluck('id');
        abort_if(!$accIds->contains($booking->accommodation_id), 403);
        abort_if($booking->status === 'cancelled', 422);
        $booking->update(['status' => 'cancelled']);
        return back()->with('status', 'رزرو لغو شد.');
    }
}
