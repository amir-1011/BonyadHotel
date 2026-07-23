<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AccommodationResource;
use App\Models\Accommodation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = $request->user()
            ->favorites()
            ->with(['city.province'])
            ->where('is_active', true)
            ->latest('user_favorites.created_at')
            ->get();

        return response()->json([
            'data' => AccommodationResource::collection($favorites),
        ]);
    }

    public function toggle(Request $request, Accommodation $accommodation): JsonResponse
    {
        $user = $request->user();

        if ($user->favorites()->where('accommodation_id', $accommodation->id)->exists()) {
            $user->favorites()->detach($accommodation->id);
            $favorited = false;
        } else {
            $user->favorites()->attach($accommodation->id);
            $favorited = true;
        }

        return response()->json(['favorited' => $favorited]);
    }
}
