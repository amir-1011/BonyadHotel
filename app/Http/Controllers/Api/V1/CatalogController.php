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

    public function provinces(): JsonResponse
    {
        $provinces = Province::orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $provinces]);
    }
}
