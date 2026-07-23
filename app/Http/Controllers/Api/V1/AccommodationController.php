<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AccommodationResource;
use App\Models\Accommodation;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class AccommodationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Accommodation::with('city.province')
            ->where('is_active', true);

        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        } elseif ($request->filled('province_id')) {
            $query->whereHas('city', fn ($q) => $q->where('province_id', $request->province_id));
        } elseif ($request->filled('lat') && $request->filled('lng')) {
            $lat    = (float) $request->lat;
            $lng    = (float) $request->lng;
            $radius = max(1, min(500, (float) $request->input('radius', 30)));
            $latDelta = $radius / 111.0;
            $lngDelta = $radius / (111.0 * cos(deg2rad($lat)));

            $query->whereNotNull('lat')->whereNotNull('lng')
                ->whereBetween('lat', [$lat - $latDelta, $lat + $latDelta])
                ->whereBetween('lng', [$lng - $lngDelta, $lng + $lngDelta]);
        }

        if ($request->filled('guests')) {
            $query->where('capacity', '>=', (int) $request->guests);
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
                            ->orWhere(fn ($i2) => $i2->where('check_in', '<=', $checkIn)->where('check_out', '>=', $checkOut));
                    });
            });
        }

        $perPage = min(50, max(1, $request->integer('per_page', 12)));

        if ($request->filled('lat') && $request->filled('lng')) {
            $lat    = (float) $request->lat;
            $lng    = (float) $request->lng;
            $radius = max(1, min(500, (float) $request->input('radius', 30)));

            $all = $query->latest()->get()->filter(function ($a) use ($lat, $lng, $radius) {
                $R    = 6371;
                $dLat = deg2rad($a->lat - $lat);
                $dLng = deg2rad($a->lng - $lng);
                $h    = sin($dLat / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($a->lat)) * sin($dLng / 2) ** 2;

                return 2 * $R * asin(sqrt($h)) <= $radius;
            })->values();

            $page = Paginator::resolveCurrentPage();
            $accommodations = new LengthAwarePaginator(
                $all->forPage($page, $perPage)->values(),
                $all->count(),
                $perPage,
                $page,
            );
        } else {
            $accommodations = $query->latest()->paginate($perPage);
        }

        return response()->json([
            'data' => AccommodationResource::collection($accommodations),
            'meta' => [
                'current_page' => $accommodations->currentPage(),
                'last_page'    => $accommodations->lastPage(),
                'per_page'     => $accommodations->perPage(),
                'total'        => $accommodations->total(),
            ],
        ]);
    }

    public function show(Request $request, Accommodation $accommodation): JsonResponse
    {
        abort_if(!$accommodation->is_active, 404);

        $accommodation->load([
            'city.province',
            'roomTypes' => fn ($q) => $q->where('is_active', true)
                ->with(['rates' => fn ($q) => $q->where('is_active', true)->orderBy('price_per_night')]),
        ]);

        return response()->json([
            'data' => new AccommodationResource($accommodation),
            'room_types' => $accommodation->roomTypes->map(fn (RoomType $rt) => [
                'id'         => $rt->id,
                'name'       => $rt->name,
                'capacity'   => (int) $rt->capacity,
                'room_count' => (int) $rt->room_count,
                'rates'      => $rt->rates->map(fn ($rate) => [
                    'id'              => $rate->id,
                    'name'            => $rate->name,
                    'price_per_night' => (int) $rate->price_per_night,
                ]),
            ]),
            'reviews' => $accommodation->reviews()->where('is_visible', true)
                ->with('user:id,name')
                ->latest()
                ->limit(20)
                ->get(['id', 'user_id', 'rating', 'comment', 'created_at']),
        ]);
    }
}
