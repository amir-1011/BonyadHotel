<?php

namespace App\Http\Controllers;

use App\Models\RoomTypeCategory;
use App\Services\RoomTypeCategoryCatalogService;
use App\Support\CatalogPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomTypeCategoryCatalogController extends Controller
{
    public function store(Request $request, RoomTypeCategoryCatalogService $catalog): JsonResponse
    {
        $user = $this->staffUser($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $category = $catalog->add($data['name'], $user->id);

        return response()->json($this->categoryPayload($category, $user));
    }

    public function update(Request $request, RoomTypeCategory $roomTypeCategory, RoomTypeCategoryCatalogService $catalog): JsonResponse
    {
        $user = $this->staffUser($request);

        if (!CatalogPermissions::canEdit($user, $roomTypeCategory->created_by)) {
            return response()->json(['message' => 'شما اجازه ویرایش این نوع اتاق را ندارید.'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $oldName = $roomTypeCategory->name;
        $category = $catalog->rename($roomTypeCategory, $data['name']);

        return response()->json(array_merge($this->categoryPayload($category, $user), [
            'old_name' => $oldName,
        ]));
    }

    public function destroy(Request $request, RoomTypeCategory $roomTypeCategory, RoomTypeCategoryCatalogService $catalog): JsonResponse
    {
        $user = $this->staffUser($request);

        if (!CatalogPermissions::canDelete($user, $roomTypeCategory->created_by)) {
            return response()->json(['message' => 'شما اجازه حذف این نوع اتاق را ندارید.'], 403);
        }

        $name = $roomTypeCategory->name;
        $catalog->remove($roomTypeCategory);

        return response()->json(['ok' => true, 'name' => $name]);
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryPayload(RoomTypeCategory $category, $user): array
    {
        return [
            'id'         => $category->id,
            'name'       => $category->name,
            'can_edit'   => CatalogPermissions::canEdit($user, $category->created_by),
            'can_delete' => CatalogPermissions::canDelete($user, $category->created_by),
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
