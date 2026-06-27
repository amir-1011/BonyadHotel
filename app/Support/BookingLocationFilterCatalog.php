<?php

namespace App\Support;

use App\Models\City;
use App\Models\County;
use App\Models\Province;
use Illuminate\Support\Collection;

class BookingLocationFilterCatalog
{
    /**
     * @param  array<int>|null  $scopedAccommodationIds
     * @return Collection<int, Province>
     */
    public function provinces(?array $scopedAccommodationIds = null): Collection
    {
        $query = Province::query()->orderBy('name');

        if ($scopedAccommodationIds !== null) {
            $query->whereHas('cities.accommodations', fn ($q) => $q->whereIn('accommodations.id', $scopedAccommodationIds));
        }

        return $query->get(['id', 'name']);
    }

    /**
     * @param  array<int>|null  $scopedAccommodationIds
     * @return Collection<int, City>
     */
    public function cities(?string $provinceId, ?array $scopedAccommodationIds = null): Collection
    {
        if ($provinceId === null || $provinceId === '') {
            return collect();
        }

        $query = City::query()
            ->where('province_id', (int) $provinceId)
            ->orderBy('name');

        if ($scopedAccommodationIds !== null) {
            $query->whereHas('accommodations', fn ($q) => $q->whereIn('id', $scopedAccommodationIds));
        }

        return $query->get(['id', 'name', 'province_id']);
    }

    /**
     * @param  array<int>|null  $scopedAccommodationIds
     * @return Collection<int, County>
     */
    public function counties(?string $provinceId, ?array $scopedAccommodationIds = null): Collection
    {
        if ($provinceId === null || $provinceId === '') {
            return collect();
        }

        $query = County::query()
            ->where('province_id', (int) $provinceId)
            ->orderBy('name');

        if ($scopedAccommodationIds !== null) {
            $query->whereHas('accommodations', fn ($q) => $q->whereIn('id', $scopedAccommodationIds));
        }

        return $query->get(['id', 'name', 'province_id']);
    }
}
