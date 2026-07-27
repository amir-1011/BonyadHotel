<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function cities(Province $province): JsonResponse
    {
        $cities = $province->cities()->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $cities]);
    }

    public function locations(): JsonResponse
    {
        $provinces = Province::query()
            ->with(['cities' => fn ($query) => $query->orderBy('name')->select('id', 'name', 'province_id')])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $province) => [
                'id' => $province->id,
                'name' => $province->name,
                'cities' => $province->cities->map(fn ($city) => [
                    'id' => $city->id,
                    'name' => $city->name,
                ])->values(),
            ])
            ->values();

        return response()->json(['data' => $provinces]);
    }

    public function provinces(): JsonResponse
    {
        $provinces = Province::orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $provinces]);
    }
}
