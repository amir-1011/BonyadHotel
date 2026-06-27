<?php

namespace App\Http\Controllers;

use App\Models\RoomTypeAmenity;
use App\Models\RoomTypeCategory;
use App\Services\RoomTypeAmenityCatalogService;
use App\Services\RoomTypeCategoryCatalogService;
use App\Support\CatalogPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomTypeAmenityCatalogController extends Controller
{
    public function store(Request $request, RoomTypeAmenityCatalogService $catalog): JsonResponse
    {
        $user = $this->staffUser($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $amenity = $catalog->add($data['name'], $user->id);

        return response()->json($this->amenityPayload($amenity, $user));
    }

    public function destroy(Request $request, RoomTypeAmenity $roomTypeAmenity, RoomTypeAmenityCatalogService $catalog): JsonResponse
    {
        $user = $this->staffUser($request);

        if (!$catalog->canDelete($user, $roomTypeAmenity)) {
            return response()->json(['message' => 'شما اجازه حذف این امکان را ندارید.'], 403);
        }

        $name = $roomTypeAmenity->name;
        $catalog->remove($roomTypeAmenity);

        return response()->json(['ok' => true, 'name' => $name]);
    }

    /**
     * @return array<string, mixed>
     */
    private function amenityPayload(RoomTypeAmenity $amenity, $user): array
    {
        return [
            'id'         => $amenity->id,
            'name'       => $amenity->name,
            'can_delete' => CatalogPermissions::canDelete($user, $amenity->created_by),
        ];
    }

    private function staffUser(Request $request)
    {
        $user = $request->user();

        if (!$user || (!$user->hasRole('super_admin') && !$user->hasRole('host'))) {
            abort(403);
        }

        return $user;
    }
}
