<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Accommodation;
use App\Models\City;
use App\Exports\AdminBookingsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with('user', 'accommodation.city', 'roomType');

        // --- Text search ---
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($w) use ($s) {
                $w->where('tracking_code', 'like', "%$s%")
                  ->orWhereHas('user', fn($q) => $q->where('name','like',"%$s%")->orWhere('mobile','like',"%$s%"))
                  ->orWhereHas('accommodation', fn($q) => $q->where('name','like',"%$s%"));
            });
        }

        // --- Status ---
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // --- Accommodation ---
        if ($request->filled('accommodation_id')) {
            $query->where('accommodation_id', $request->accommodation_id);
        }

        // --- City (via accommodation) ---
        if ($request->filled('city_id')) {
            $query->whereHas('accommodation', fn($q) => $q->where('city_id', $request->city_id));
        }

        // --- Check-in date range ---
        if ($request->filled('check_in_from') && ($d = $this->toGregorian($request->check_in_from))) {
            $query->whereDate('check_in', '>=', $d);
        }
        if ($request->filled('check_in_to') && ($d = $this->toGregorian($request->check_in_to))) {
            $query->whereDate('check_in', '<=', $d);
        }

        // --- Check-out date range ---
        if ($request->filled('check_out_from') && ($d = $this->toGregorian($request->check_out_from))) {
            $query->whereDate('check_out', '>=', $d);
        }
        if ($request->filled('check_out_to') && ($d = $this->toGregorian($request->check_out_to))) {
            $query->whereDate('check_out', '<=', $d);
        }

        // --- Nights range ---
        if ($request->filled('nights_min')) {
            $query->where('nights', '>=', (int)$request->nights_min);
        }
        if ($request->filled('nights_max')) {
            $query->where('nights', '<=', (int)$request->nights_max);
        }

        // --- Price range ---
        if ($request->filled('price_min')) {
            $query->where('total_price', '>=', (int)str_replace(',', '', $request->price_min));
        }
        if ($request->filled('price_max')) {
            $query->where('total_price', '<=', (int)str_replace(',', '', $request->price_max));
        }

        // --- Guests ---
        if ($request->filled('guests_min')) {
            $query->where('guests', '>=', (int)$request->guests_min);
        }

        // --- Discount only ---
        if ($request->boolean('has_discount')) {
            $query->where('discount_percentage', '>', 0);
        }

        // --- Sorting ---
        $sortable = ['id', 'check_in', 'check_out', 'nights', 'total_price', 'guests', 'created_at'];
        $sort = in_array($request->sort, $sortable) ? $request->sort : 'created_at';
        $dir  = $request->dir === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        // --- Total for current filter (before pagination) ---
        $totalFiltered = (clone $query)->sum('total_price');
        $countFiltered = (clone $query)->count();

        $bookings       = $query->paginate(25)->withQueryString();
        $accommodations = Accommodation::orderBy('name')->get(['id','name']);
        $cities         = City::orderBy('name')->get(['id','name']);

        return view('admin.bookings.index', compact(
            'bookings', 'accommodations', 'cities',
            'totalFiltered', 'countFiltered', 'sort', 'dir'
        ));
    }

    public function show(Booking $booking)
    {
        $booking->load('user', 'accommodation.city.province');
        return view('admin.bookings.show', compact('booking'));
    }

    public function export(Request $request)
    {
        $filters = $request->only([
            'search', 'status', 'accommodation_id', 'city_id',
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

    /** Convert a Jalali date string (supports Persian/Arabic digits) to Gregorian (2024-05-15) */
    private function toGregorian(?string $jalali): ?string
    {
        if (!$jalali) return null;
        try {
            // Convert Persian/Arabic-Indic digits to ASCII digits
            $normalized = strtr(trim($jalali), [
                '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
                '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
                '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            ]);
            return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
