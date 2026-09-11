<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Accommodation;
use App\Models\Hall;
use App\Models\HallType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

trait SavesAccommodationHalls
{
    /**
     * @return array<string, mixed>
     */
    protected function validatedHall(Request $request): array
    {
        $data = $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'hall_type_id' => ['required', 'integer', Rule::exists('hall_types', 'id')],
            'capacity'     => ['required', 'integer', 'min:1', 'max:5000'],
            'description'  => ['nullable', 'string', 'max:2000'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            'amenities'    => ['nullable', 'array'],
            'amenities.*'  => ['string', 'max:60'],
            'is_active'    => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['amenities'] = array_values(array_filter($data['amenities'] ?? []));

        return $data;
    }

    protected function storeHall(Request $request, Accommodation $accommodation): Hall
    {
        return $accommodation->halls()->create($this->validatedHall($request));
    }

    protected function updateHall(Request $request, Hall $hall): Hall
    {
        $hall->update($this->validatedHall($request));

        return $hall->fresh();
    }

    protected function destroyHall(Hall $hall): void
    {
        if ($hall->hasBlockingPrograms()) {
            throw new \RuntimeException('این سالن رویداد فعال یا آینده دارد و قابل حذف نیست.');
        }

        $hall->delete();
    }

    protected function hallTypeExists(int $id): bool
    {
        return HallType::query()->whereKey($id)->exists();
    }
}
