<?php

namespace App\Services;

use App\Models\HallAmenity;
use App\Models\Hall;
use App\Models\User;
use App\Support\CatalogPermissions;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HallAmenityCatalogService
{
    public function __construct(
        private readonly RoomTypeAmenityCatalogService $normalizer,
    ) {}

    public function normalize(string $value): string
    {
        return $this->normalizer->normalize($value);
    }

    /**
     * @return Collection<int, HallAmenity>
     */
    public function allOrdered(): Collection
    {
        $this->ensureSeeded();

        return HallAmenity::query()
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

    public function add(string $name, ?int $createdBy = null): HallAmenity
    {
        $this->ensureSeeded();

        $name = $this->normalize($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'نام امکان را وارد کنید.']);
        }

        if (mb_strlen($name) > 60) {
            throw ValidationException::withMessages(['name' => 'نام امکان نباید بیشتر از ۶۰ کاراکتر باشد.']);
        }

        $existing = HallAmenity::query()->where('name', $name)->first();
        if ($existing) {
            return $existing;
        }

        $maxSort = (int) HallAmenity::query()->max('sort_order');

        return HallAmenity::create([
            'name'       => $name,
            'sort_order' => $maxSort + 1,
            'created_by' => $createdBy,
        ]);
    }

    public function canDelete(?User $user, HallAmenity $amenity): bool
    {
        return CatalogPermissions::canDelete($user, $amenity->created_by);
    }

    public function remove(HallAmenity $amenity): void
    {
        $name = $amenity->name;
        $amenity->delete();

        Hall::query()->whereNotNull('amenities')->get()->each(function (Hall $hall) use ($name) {
            $amenities = collect($hall->amenities ?? [])
                ->reject(fn ($item) => $item === $name)
                ->values()
                ->all();

            if ($amenities !== ($hall->amenities ?? [])) {
                $hall->update(['amenities' => $amenities]);
            }
        });
    }

    private function ensureSeeded(): void
    {
        if (HallAmenity::query()->exists()) {
            return;
        }

        $sort = 0;
        $seen = [];

        foreach (config('halls.amenities', []) as $name) {
            $name = $this->normalize((string) $name);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            HallAmenity::create(['name' => $name, 'sort_order' => $sort++]);
        }
    }
}
