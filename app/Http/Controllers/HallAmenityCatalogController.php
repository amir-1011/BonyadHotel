<?php

namespace App\Http\Controllers;

use App\Models\HallAmenity;
use App\Services\HallAmenityCatalogService;
use App\Support\CatalogPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HallAmenityCatalogController extends Controller
{
    public function store(Request $request, HallAmenityCatalogService $catalog): JsonResponse
    {
        $user = $this->staffUser($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $amenity = $catalog->add($data['name'], $user->id);

        return response()->json($this->amenityPayload($amenity, $user));
    }

    public function destroy(Request $request, HallAmenity $hallAmenity, HallAmenityCatalogService $catalog): JsonResponse
    {
        $user = $this->staffUser($request);

        if (! $catalog->canDelete($user, $hallAmenity)) {
            return response()->json(['message' => 'شما اجازه حذف این امکان را ندارید.'], 403);
        }

        $name = $hallAmenity->name;
        $catalog->remove($hallAmenity);

        return response()->json(['ok' => true, 'name' => $name]);
    }

    /**
     * @return array<string, mixed>
     */
    private function amenityPayload(HallAmenity $amenity, $user): array
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

        if (! $user || (! $user->hasRole('super_admin') && ! $user->hasRole('host'))) {
            abort(403);
        }

        return $user;
    }
}
