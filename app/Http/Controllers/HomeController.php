<?php

namespace App\Http\Controllers;

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

        return view('home', compact('provinces', 'citiesForMap'));
    }
}
