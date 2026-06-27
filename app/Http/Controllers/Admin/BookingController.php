<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Exports\AdminBookingsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function show(Booking $booking)
    {
        $booking->load('user', 'accommodation.city.province');
        return view('admin.bookings.show', compact('booking'));
    }

    public function export(Request $request)
    {
        $filters = $request->only([
            'search', 'status', 'accommodation_id', 'province_id', 'city_id', 'county_id',
            'service_catalog_id', 'service_catalog_variant_id',
            'check_in_from', 'check_in_to', 'check_out_from', 'check_out_to',
            'nights_min', 'nights_max', 'price_min', 'price_max',
            'guests_min', 'has_discount',
        ]);
        return Excel::download(new AdminBookingsExport($filters), 'bookings.xlsx');
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $request->validate(['status' => ['required', 'in:pending,confirmed,cancelled']]);
        $booking->update(['status' => $request->status]);
        return back()->with('status', 'وضعیت رزرو به‌روز شد.');
    }

}
