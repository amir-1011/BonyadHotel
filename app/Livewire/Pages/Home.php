<?php

namespace App\Livewire\Pages;

use App\Models\Accommodation;
use App\Models\City;
use App\Models\Province;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'ایثار — رزرو اقامتگاه'])]
class Home extends Component
{
    public function render()
    {
        $provinces = Cache::remember('home_provinces', 1800, fn() => Province::orderBy('name')->get());

        $citiesForMap = Cache::remember('home_cities_map', 1800, function () {
            return City::with('province')->get()->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'province' => $c->province->name,
                'lat'      => $c->accommodations()->whereNotNull('lat')->value('lat'),
                'lng'      => $c->accommodations()->whereNotNull('lng')->value('lng'),
            ])->filter(fn($c) => $c['lat'] && $c['lng'])->values();
        });

        $featured = Cache::remember('home_featured', 600, fn() =>
            Accommodation::with(['city.province', 'reviews'])
                ->where('is_active', true)
                ->latest()
                ->take(12)
                ->get()
        );

        $popularCities = Cache::remember('home_popular_cities', 1800, fn() =>
            City::withCount(['accommodations' => fn($q) => $q->where('is_active', true)])
                ->get()
                ->filter(fn($c) => $c->accommodations_count > 0)
                ->sortByDesc('accommodations_count')
                ->take(8)
                ->values()
        );

        return view('home', compact('provinces', 'citiesForMap', 'featured', 'popularCities'));
    }
}
