<?php

namespace App\Support;

use App\Models\Accommodation;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Support\Collection;

class BookingRoomFilterCatalog
{
    /**
     * Distinct selectable room categories (bed_type) for filter dropdowns.
     *
     * @param  array<int>|null  $scopedAccommodationIds
     * @return Collection<int, string>
     */
    public function categories(
        ?string $accommodationId,
        ?string $provinceId = null,
        ?string $cityId = null,
        ?string $countyId = null,
        ?array $scopedAccommodationIds = null,
    ): Collection {
        $accommodationIds = $this->resolveAccommodationIds(
            $accommodationId,
            $provinceId,
            $cityId,
            $countyId,
            $scopedAccommodationIds,
        );

        $query = RoomType::query()
            ->whereNotNull('bed_type')
            ->where('bed_type', '!=', '');

        if ($accommodationIds !== null) {
            if ($accommodationIds === []) {
                return collect();
            }

            $query->whereIn('accommodation_id', $accommodationIds);
        }

        return $query
            ->orderBy('bed_type')
            ->distinct()
            ->pluck('bed_type')
            ->values();
    }

    /**
     * @param  array<int>|null  $scopedAccommodationIds
     * @return Collection<int, Room>
     */
    public function rooms(
        ?string $accommodationId,
        ?string $roomCategory,
        ?string $provinceId = null,
        ?string $cityId = null,
        ?string $countyId = null,
        ?array $scopedAccommodationIds = null,
    ): Collection {
        $accommodationIds = $this->resolveAccommodationIds(
            $accommodationId,
            $provinceId,
            $cityId,
            $countyId,
            $scopedAccommodationIds,
        );

        $query = Room::query()
            ->with('roomType:id,name,bed_type,accommodation_id')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($roomCategory !== null && $roomCategory !== '') {
            $query->whereHas('roomType', fn ($q) => $q->where('bed_type', $roomCategory));
        } elseif ($accommodationIds !== null) {
            if ($accommodationIds === []) {
                return collect();
            }

            $query->whereHas('roomType', fn ($q) => $q->whereIn('accommodation_id', $accommodationIds));
        }

        return $query->get(['id', 'name', 'room_type_id']);
    }

    public function shouldShowAccommodationInLabels(?string $accommodationId): bool
    {
        return $accommodationId === null || $accommodationId === '';
    }

    /**
     * @param  array<int>|null  $scopedAccommodationIds
     * @return array<int>|null
     */
    private function resolveAccommodationIds(
        ?string $accommodationId,
        ?string $provinceId,
        ?string $cityId,
        ?string $countyId,
        ?array $scopedAccommodationIds,
    ): ?array {
        if ($accommodationId !== null && $accommodationId !== '') {
            $id = (int) $accommodationId;

            if ($scopedAccommodationIds !== null && !in_array($id, $scopedAccommodationIds, true)) {
                return [];
            }

            return [$id];
        }

        $hasLocationFilter = ($provinceId !== null && $provinceId !== '')
            || ($cityId !== null && $cityId !== '')
            || ($countyId !== null && $countyId !== '');

        if (!$hasLocationFilter) {
            return $scopedAccommodationIds;
        }

        $query = Accommodation::query()->select('id');

        if ($scopedAccommodationIds !== null) {
            $query->whereIn('id', $scopedAccommodationIds);
        }

        if ($provinceId !== null && $provinceId !== '') {
            $query->whereHas('city', fn ($q) => $q->where('province_id', (int) $provinceId));
        }

        if ($cityId !== null && $cityId !== '') {
            $query->where('city_id', (int) $cityId);
        }

        if ($countyId !== null && $countyId !== '') {
            $query->where('county_id', (int) $countyId);
        }

        return $query->pluck('id')->all();
    }
}
