<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeAmenity;
use App\Models\User;
use App\Support\CatalogPermissions;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RoomTypeAmenityCatalogService
{
    public function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', '‌'], ['ی', 'ک', ' '], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return Collection<int, RoomTypeAmenity>
     */
    public function allOrdered(): Collection
    {
        $this->ensureSeeded();

        return RoomTypeAmenity::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return $this->allOrdered()->pluck('name')->all();
    }

    public function add(string $name, ?int $createdBy = null): RoomTypeAmenity
    {
        $this->ensureSeeded();

        $name = $this->normalize($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'نام امکان را وارد کنید.']);
        }

        if (mb_strlen($name) > 60) {
            throw ValidationException::withMessages(['name' => 'نام امکان نباید بیشتر از ۶۰ کاراکتر باشد.']);
        }

        $existing = RoomTypeAmenity::query()->where('name', $name)->first();
        if ($existing) {
            return $existing;
        }

        $maxSort = (int) RoomTypeAmenity::query()->max('sort_order');

        return RoomTypeAmenity::create([
            'name'       => $name,
            'sort_order' => $maxSort + 1,
            'created_by' => $createdBy,
        ]);
    }

    public function canDelete(?User $user, RoomTypeAmenity $amenity): bool
    {
        return CatalogPermissions::canDelete($user, $amenity->created_by);
    }

    public function remove(RoomTypeAmenity $amenity): void
    {
        $amenity->delete();
    }

    private function ensureSeeded(): void
    {
        if (RoomTypeAmenity::query()->exists()) {
            return;
        }

        $sort = 0;
        $seen = [];

        foreach (config('room_types.amenities', []) as $name) {
            $name = $this->normalize((string) $name);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            RoomTypeAmenity::create(['name' => $name, 'sort_order' => $sort++]);
        }

        $this->importDiscovered($seen, $sort);
    }

    /**
     * @param  array<string, true>  $seen
     */
    private function importDiscovered(array $seen, int $sort): void
    {
        RoomType::query()
            ->whereNotNull('amenities')
            ->select(['amenities'])
            ->chunkById(100, function ($roomTypes) use (&$seen, &$sort) {
                foreach ($roomTypes as $roomType) {
                    foreach ($roomType->amenities ?? [] as $name) {
                        $name = $this->normalize((string) $name);
                        if ($name === '' || isset($seen[$name])) {
                            continue;
                        }
                        $seen[$name] = true;
                        RoomTypeAmenity::create(['name' => $name, 'sort_order' => $sort++]);
                    }
                }
            });

        Room::query()
            ->whereNotNull('amenities')
            ->select(['amenities'])
            ->chunkById(100, function ($rooms) use (&$seen, &$sort) {
                foreach ($rooms as $room) {
                    foreach ($room->amenities ?? [] as $name) {
                        $name = $this->normalize((string) $name);
                        if ($name === '' || isset($seen[$name])) {
                            continue;
                        }
                        $seen[$name] = true;
                        RoomTypeAmenity::create(['name' => $name, 'sort_order' => $sort++]);
                    }
                }
            });
    }
}
