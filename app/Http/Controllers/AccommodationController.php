<?php

namespace App\Http\Controllers;

use App\Models\Accommodation;
use App\Models\City;
use App\Models\Province;
use Illuminate\Http\Request;

class AccommodationController extends Controller
{
    public function index(Request $request)
    {
        $provinces = Province::orderBy('name')->get();
        $cities    = collect();

        $query = Accommodation::with('city.province')
            ->where('is_active', true);

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        } elseif ($request->filled('province_id')) {
            $query->whereHas('city', fn($q) => $q->where('province_id', $request->province_id));
            $cities = City::where('province_id', $request->province_id)->orderBy('name')->get();
        } elseif ($request->filled('lat') && $request->filled('lng')) {
            $lat    = (float) $request->lat;
            $lng    = (float) $request->lng;
            $radius = max(1, min(500, (float) $request->input('radius', 30)));

            // Bounding box approximation (1 degree lat ≈ 111 km)
            $latDelta = $radius / 111.0;
            $lngDelta = $radius / (111.0 * cos(deg2rad($lat)));

            $query->whereNotNull('lat')
                  ->whereNotNull('lng')
                  ->whereBetween('lat', [$lat - $latDelta, $lat + $latDelta])
                  ->whereBetween('lng', [$lng - $lngDelta, $lng + $lngDelta]);

            // Precise Haversine filter in PHP after DB fetch
            $haversine = function ($a) use ($lat, $lng, $radius) {
                $R   = 6371;
                $dLat = deg2rad($a->lat - $lat);
                $dLng = deg2rad($a->lng - $lng);
                $h   = sin($dLat / 2) ** 2
                     + cos(deg2rad($lat)) * cos(deg2rad($a->lat)) * sin($dLng / 2) ** 2;
                return 2 * $R * asin(sqrt($h)) <= $radius;
            };

            $accommodations = $query->latest()->get()->filter($haversine);
            $accommodations = new \Illuminate\Pagination\LengthAwarePaginator(
                $accommodations->forPage(\Illuminate\Pagination\Paginator::resolveCurrentPage(), 12)->values(),
                $accommodations->count(),
                12,
                null,
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => $request->query()]
            );

            return view('accommodations.index', compact('accommodations', 'provinces', 'cities'));
        }

        if ($request->filled('guests')) {
            $query->where('capacity', '>=', $request->guests);
        }

        if ($request->boolean('wheelchair')) {
            $query->whereJsonContains('amenities', 'مناسب ویلچر');
        }

        $checkIn  = $request->input('check_in');
        $checkOut = $request->input('check_out');

        if ($checkIn && $checkOut) {
            $query->whereDoesntHave('bookings', function ($q) use ($checkIn, $checkOut) {
                $q->whereIn('status', ['confirmed', 'pending'])
                  ->where(function ($inner) use ($checkIn, $checkOut) {
                      $inner->whereBetween('check_in', [$checkIn, $checkOut])
                            ->orWhereBetween('check_out', [$checkIn, $checkOut])
                            ->orWhere(function ($i2) use ($checkIn, $checkOut) {
                                $i2->where('check_in', '<=', $checkIn)
                                   ->where('check_out', '>=', $checkOut);
                            });
                  });
            });
        }

        $accommodations = $query->latest()->paginate(12)->withQueryString();

        return view('accommodations.index', compact(
            'accommodations', 'provinces', 'cities'
        ));
    }

    public function show(Accommodation $accommodation)
    {
        $accommodation->load('city.province');

        $reviews = $accommodation->reviews()->where('is_visible', true)
            ->with('user')->latest()->get();

        // Room types with active rates
        $roomTypes = $accommodation->roomTypes()
            ->where('is_active', true)
            ->with(['rates' => fn($q) => $q->where('is_active', true)->orderBy('price_per_night')])
            ->get();

        // Can current user leave a review?
        $canReview = false;
        if (auth()->check()) {
            $canReview = \App\Models\Booking::where('user_id', auth()->id())
                ->where('accommodation_id', $accommodation->id)
                ->where('status', 'confirmed')
                ->where('check_out', '<', now()->toDateString())
                ->exists();
        }

        $userReview = auth()->check()
            ? $accommodation->reviews()->where('user_id', auth()->id())->first()
            : null;

        return view('accommodations.show', compact('accommodation', 'reviews', 'canReview', 'userReview', 'roomTypes'));
    }

    public function citiesByProvince(Province $province)
    {
        return response()->json($province->cities()->orderBy('name')->get(['id', 'name']));
    }
}
