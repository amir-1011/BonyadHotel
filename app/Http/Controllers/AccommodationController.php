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

        return view('accommodations.show', compact('accommodation', 'reviews', 'canReview', 'userReview'));
    }

    public function citiesByProvince(Province $province)
    {
        return response()->json($province->cities()->orderBy('name')->get(['id', 'name']));
    }
}
