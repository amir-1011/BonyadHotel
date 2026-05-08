<?php

namespace App\Http\Controllers;

use App\Models\Accommodation;
use App\Models\City;
use App\Models\Province;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $provinces = Province::orderBy('name')->get();

        $citiesForMap = City::with('province')
            ->get()
            ->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'province' => $c->province->name,
                'lat'      => $c->accommodations()->whereNotNull('lat')->value('lat'),
                'lng'      => $c->accommodations()->whereNotNull('lng')->value('lng'),
            ])
            ->filter(fn($c) => $c['lat'] && $c['lng'])
            ->values();

        // Featured accommodations for Airbnb-style cards
        $featured = Accommodation::with(['city.province', 'reviews'])
            ->where('is_active', true)
            ->latest()
            ->take(12)
            ->get();

        // Popular cities (cities with accommodations)
        $popularCities = City::withCount(['accommodations' => fn($q) => $q->where('is_active', true)])
            ->having('accommodations_count', '>', 0)
            ->orderByDesc('accommodations_count')
            ->take(8)
            ->get();

        return view('home', compact('provinces', 'citiesForMap', 'featured', 'popularCities'));
    }
}
