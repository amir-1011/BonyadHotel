<?php

namespace App\Http\Controllers;

use App\Models\HallType;
use App\Services\HallTypeCatalogService;
use App\Support\CatalogPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HallTypeCatalogController extends Controller
{
    public function store(Request $request, HallTypeCatalogService $catalog): JsonResponse
    {
        $user = $this->staffUser($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $type = $catalog->add($data['name'], $user->id);

        return response()->json($this->typePayload($type, $user));
    }

    public function update(Request $request, HallType $hallType, HallTypeCatalogService $catalog): JsonResponse
    {
        $user = $this->staffUser($request);

        if (! CatalogPermissions::canEdit($user, $hallType->created_by)) {
            return response()->json(['message' => 'شما اجازه ویرایش این نوع سالن را ندارید.'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
        ]);

        $oldName = $hallType->name;
        $type = $catalog->rename($hallType, $data['name']);

        return response()->json(array_merge($this->typePayload($type, $user), [
            'old_name' => $oldName,
        ]));
    }

    public function destroy(Request $request, HallType $hallType, HallTypeCatalogService $catalog): JsonResponse
    {
        $user = $this->staffUser($request);

        if (! CatalogPermissions::canDelete($user, $hallType->created_by)) {
            return response()->json(['message' => 'شما اجازه حذف این نوع سالن را ندارید.'], 403);
        }

        $name = $hallType->name;
        $catalog->remove($hallType);

        return response()->json(['ok' => true, 'name' => $name]);
    }

    /**
     * @return array<string, mixed>
     */
    private function typePayload(HallType $type, $user): array
    {
        return [
            'id'         => $type->id,
            'name'       => $type->name,
            'can_edit'   => CatalogPermissions::canEdit($user, $type->created_by),
            'can_delete' => CatalogPermissions::canDelete($user, $type->created_by),
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
