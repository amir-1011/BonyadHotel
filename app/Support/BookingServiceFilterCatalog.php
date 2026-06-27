<?php

namespace App\Support;

use App\Models\Accommodation;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogVariant;
use Illuminate\Support\Collection;

class BookingServiceFilterCatalog
{
    /**
     * @param  array<int>|null  $scopedAccommodationIds
     * @return Collection<int, ServiceCatalog>
     */
    public function parentServices(
        ?string $accommodationId,
        ?string $provinceId,
        ?string $cityId,
        ?string $countyId,
        ?array $scopedAccommodationIds = null,
    ): Collection {
        $accommodationIds = $this->resolveAccommodationIds(
            $accommodationId,
            $provinceId,
            $cityId,
            $countyId,
            $scopedAccommodationIds,
        );

        $query = ServiceCatalog::query()
            ->active()
            ->ordered()
            ->with('accommodation:id,name');

        if ($accommodationIds !== null) {
            if ($accommodationIds === []) {
                return collect();
            }

            $query->whereIn('accommodation_id', $accommodationIds);
        }

        return $query->get(['id', 'name', 'key', 'accommodation_id']);
    }

    /** @return Collection<int, ServiceCatalogVariant> */
    public function variants(?string $serviceCatalogId): Collection
    {
        if ($serviceCatalogId === null || $serviceCatalogId === '') {
            return collect();
        }

        return ServiceCatalogVariant::query()
            ->where('service_catalog_id', (int) $serviceCatalogId)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'service_catalog_id']);
    }

    public function parentServiceLabel(ServiceCatalog $service, bool $showAccommodation): string
    {
        if ($showAccommodation && $service->accommodation) {
            return $service->accommodation->name . ' — ' . $service->name;
        }

        return $service->name;
    }

    public function shouldShowAccommodationInLabels(?string $accommodationId): bool
    {
        return $accommodationId === null || $accommodationId === '';
    }

    /**
     * @param  array<int>|null  $scopedAccommodationIds
     * @return array<int>|null  null = no restriction
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
