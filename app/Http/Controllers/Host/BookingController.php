<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Exports\AdminBookingsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $accIds = Auth::user()->managedAccommodationIds();

        $query = Booking::whereIn('accommodation_id', $accIds)
            ->with('user', 'accommodation');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('accommodation_id') && $accIds->contains($request->accommodation_id)) {
            $query->where('accommodation_id', $request->accommodation_id);
        }

        $myAccommodations = Auth::user()->managedAccommodationOptions();
        $bookings = $query->latest()->paginate(20)->withQueryString();
        return view('host.bookings.index', compact('bookings', 'myAccommodations'));
    }

    public function export(Request $request)
    {
        $filters = $request->only([
            'search', 'status', 'accommodation_id', 'city_id',
            'check_in_from', 'check_in_to', 'check_out_from', 'check_out_to',
            'nights_min', 'nights_max', 'price_min', 'price_max',
            'guests_min', 'has_discount',
        ]);

        $accommodationIds = Auth::user()->managedAccommodationIds()->all();

        return Excel::download(
            new AdminBookingsExport($filters, $accommodationIds),
            'host-bookings.xlsx'
        );
    }

    public function show(Booking $booking)
    {
        $accIds = Auth::user()->managedAccommodationIds();
        abort_if(!$accIds->contains($booking->accommodation_id), 403);
        $booking->load('user', 'accommodation.city');
        return view('host.bookings.show', compact('booking'));
    }

    public function confirm(Booking $booking)
    {
        $accIds = Auth::user()->managedAccommodationIds();
        abort_if(!$accIds->contains($booking->accommodation_id), 403);
        abort_if($booking->status !== 'pending', 422);
        $booking->update(['status' => 'confirmed']);
        return back()->with('status', 'رزرو تأیید شد.');
    }

    public function cancel(Booking $booking)
    {
        $accIds = Auth::user()->managedAccommodationIds();
        abort_if(!$accIds->contains($booking->accommodation_id), 403);
        abort_if($booking->status === 'cancelled', 422);
        $booking->update(['status' => 'cancelled']);
        return back()->with('status', 'رزرو لغو شد.');
    }
}
