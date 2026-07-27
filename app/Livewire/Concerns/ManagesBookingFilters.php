<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;
use Livewire\WithPagination;

trait ManagesBookingFilters
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'accommodation_id')]
    public string $accommodationId = '';

    #[Url(as: 'host_id')]
    public string $hostId = '';

    #[Url(as: 'reserver_id')]
    public string $reserverId = '';

    #[Url(as: 'province_id')]
    public string $provinceId = '';

    #[Url(as: 'city_id')]
    public string $cityId = '';

    #[Url(as: 'county_id')]
    public string $countyId = '';

    #[Url(as: 'service_catalog_id')]
    public string $serviceCatalogId = '';

    #[Url(as: 'service_catalog_variant_id')]
    public string $serviceCatalogVariantId = '';

    #[Url(as: 'check_in_from')]
    public string $checkInFrom = '';

    #[Url(as: 'check_in_to')]
    public string $checkInTo = '';

    #[Url(as: 'check_out_from')]
    public string $checkOutFrom = '';

    #[Url(as: 'check_out_to')]
    public string $checkOutTo = '';

    #[Url(as: 'nights_min')]
    public string $nightsMin = '';

    #[Url(as: 'nights_max')]
    public string $nightsMax = '';

    #[Url(as: 'price_min')]
    public string $priceMin = '';

    #[Url(as: 'price_max')]
    public string $priceMax = '';

    #[Url(as: 'guests_min')]
    public string $guestsMin = '';

    #[Url(as: 'has_discount')]
    public bool $hasDiscount = false;

    #[Url(as: 'bed_type')]
    public string $roomCategory = '';

    #[Url(as: 'room_id')]
    public string $roomId = '';

    #[Url(as: 'veteran_type')]
    public string $veteranType = '';

    #[Url(as: 'booking_source')]
    public string $bookingSource = '';

    #[Url(as: 'program_type')]
    public string $programType = '';

    #[Url(as: 'program_payment_type')]
    public string $programPaymentType = '';

    #[Url(as: 'program_employer_id')]
    public string $programEmployerId = '';

    #[Url(as: 'sort')]
    public string $sort = 'created_at';

    #[Url(as: 'dir')]
    public string $dir = 'desc';

    public string $draftSearch = '';

    public string $draftStatus = '';

    public string $draftAccommodationId = '';

    public string $draftHostId = '';

    public string $draftReserverId = '';

    public string $draftProvinceId = '';

    public string $draftCityId = '';

    public string $draftCountyId = '';

    public string $draftServiceCatalogId = '';

    public string $draftServiceCatalogVariantId = '';

    public string $draftCheckInFrom = '';

    public string $draftCheckInTo = '';

    public string $draftCheckOutFrom = '';

    public string $draftCheckOutTo = '';

    public string $draftNightsMin = '';

    public string $draftNightsMax = '';

    public string $draftPriceMin = '';

    public string $draftPriceMax = '';

    public string $draftGuestsMin = '';

    public bool $draftHasDiscount = false;

    public string $draftRoomCategory = '';

    public string $draftRoomId = '';

    public string $draftVeteranType = '';

    public string $draftBookingSource = '';

    public string $draftProgramType = '';

    public string $draftProgramPaymentType = '';

    public string $draftProgramEmployerId = '';

    public function mountBookingFilters(): void
    {
        $this->syncDraftFromApplied();
    }

    public function applyFilters(): void
    {
        $this->search = trim($this->draftSearch);
        $this->status = $this->draftStatus;
        $this->accommodationId = $this->draftAccommodationId;
        $this->hostId = $this->draftHostId;
        $this->provinceId = $this->draftProvinceId;
        $this->cityId = $this->draftCityId;
        $this->countyId = $this->draftCountyId;
        $this->serviceCatalogId = $this->draftServiceCatalogId;
        $this->serviceCatalogVariantId = $this->draftServiceCatalogVariantId;
        $this->checkInFrom = $this->draftCheckInFrom;
        $this->checkInTo = $this->draftCheckInTo;
        $this->checkOutFrom = $this->draftCheckOutFrom;
        $this->checkOutTo = $this->draftCheckOutTo;
        $this->nightsMin = $this->draftNightsMin;
        $this->nightsMax = $this->draftNightsMax;
        $this->priceMin = $this->draftPriceMin;
        $this->priceMax = $this->draftPriceMax;
        $this->guestsMin = $this->draftGuestsMin;
        $this->hasDiscount = $this->draftHasDiscount;
        $this->bookingSource = $this->draftBookingSource;
        $this->programType = $this->draftProgramType;
        $this->programPaymentType = $this->draftProgramPaymentType;
        $this->programEmployerId = $this->draftProgramEmployerId;
        if ($this->draftBookingSource === 'online') {
            $this->draftReserverId = '';
        }
        $this->reserverId = $this->draftReserverId;
        $this->roomCategory = $this->draftRoomCategory;
        $this->roomId = $this->draftRoomId;
        $this->veteranType = $this->draftVeteranType;

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search', 'status', 'accommodationId', 'hostId', 'reserverId',
            'provinceId', 'cityId', 'countyId',
            'serviceCatalogId', 'serviceCatalogVariantId',
            'checkInFrom', 'checkInTo', 'checkOutFrom', 'checkOutTo',
            'nightsMin', 'nightsMax', 'priceMin', 'priceMax', 'guestsMin',
            'hasDiscount', 'roomCategory', 'roomId', 'veteranType', 'bookingSource',
            'programType', 'programPaymentType', 'programEmployerId',
        ]);
        $this->sort = 'created_at';
        $this->dir = 'desc';
        $this->syncDraftFromApplied();
        $this->resetPage();
        $this->dispatch('booking-dates-sync', dates: $this->bookingDateSyncPayload());
    }

    public function updatedDraftProvinceId(): void
    {
        $this->draftCityId = '';
        $this->draftCountyId = '';
        $this->draftServiceCatalogId = '';
        $this->draftServiceCatalogVariantId = '';
    }

    public function updatedDraftAccommodationId(): void
    {
        $this->draftReserverId = '';
        $this->draftServiceCatalogId = '';
        $this->draftServiceCatalogVariantId = '';
        $this->draftRoomCategory = '';
        $this->draftRoomId = '';
    }

    public function updatedDraftRoomCategory(): void
    {
        $this->draftRoomId = '';
    }

    public function updatedDraftBookingSource(): void
    {
        if ($this->draftBookingSource === 'online') {
            $this->draftReserverId = '';
        }
    }

    public function updatedDraftServiceCatalogId(): void
    {
        $this->draftServiceCatalogVariantId = '';
    }

    public function sortBy(string $column): void
    {
        $sortable = ['id', 'check_in', 'check_out', 'nights', 'total_price', 'guests', 'created_at'];
        if (!in_array($column, $sortable, true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->dir = $this->dir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->dir = 'asc';
        }

        $this->resetPage();
    }

    /** @return array<string, mixed> */
    protected function bookingFilterParams(): array
    {
        return [
            'search'           => $this->search,
            'status'           => $this->status,
            'accommodation_id' => $this->accommodationId,
            'host_id'          => $this->hostId,
            'reserver_id'      => $this->bookingSource === 'online' ? '' : $this->reserverId,
            'province_id'      => $this->provinceId,
            'city_id'          => $this->cityId,
            'county_id'                 => $this->countyId,
            'service_catalog_id'        => $this->serviceCatalogId,
            'service_catalog_variant_id'=> $this->serviceCatalogVariantId,
            'check_in_from'    => $this->checkInFrom,
            'check_in_to'      => $this->checkInTo,
            'check_out_from'   => $this->checkOutFrom,
            'check_out_to'     => $this->checkOutTo,
            'nights_min'       => $this->nightsMin,
            'nights_max'       => $this->nightsMax,
            'price_min'        => $this->priceMin,
            'price_max'        => $this->priceMax,
            'guests_min'       => $this->guestsMin,
            'has_discount'     => $this->hasDiscount,
            'room_category'    => $this->roomCategory,
            'room_id'          => $this->roomId,
            'veteran_type'     => $this->veteranType,
            'booking_source'   => $this->bookingSource,
            'program_type'          => $this->programType,
            'program_payment_type'  => $this->programPaymentType,
            'program_employer_id'   => $this->programEmployerId,
            'sort'             => $this->sort,
            'dir'              => $this->dir,
        ];
    }

    /** @return array<string, string> */
    protected function bookingDateSyncPayload(): array
    {
        return [
            'check_in_from'  => $this->draftCheckInFrom,
            'check_in_to'    => $this->draftCheckInTo,
            'check_out_from' => $this->draftCheckOutFrom,
            'check_out_to'   => $this->draftCheckOutTo,
        ];
    }

    protected function syncDraftFromApplied(): void
    {
        $this->draftSearch = $this->search;
        $this->draftStatus = $this->status;
        $this->draftAccommodationId = $this->accommodationId;
        $this->draftHostId = $this->hostId;
        $this->draftReserverId = $this->reserverId;
        $this->draftProvinceId = $this->provinceId;
        $this->draftCityId = $this->cityId;
        $this->draftCountyId = $this->countyId;
        $this->draftServiceCatalogId = $this->serviceCatalogId;
        $this->draftServiceCatalogVariantId = $this->serviceCatalogVariantId;
        $this->draftCheckInFrom = $this->checkInFrom;
        $this->draftCheckInTo = $this->checkInTo;
        $this->draftCheckOutFrom = $this->checkOutFrom;
        $this->draftCheckOutTo = $this->checkOutTo;
        $this->draftNightsMin = $this->nightsMin;
        $this->draftNightsMax = $this->nightsMax;
        $this->draftPriceMin = $this->priceMin;
        $this->draftPriceMax = $this->priceMax;
        $this->draftGuestsMin = $this->guestsMin;
        $this->draftHasDiscount = $this->hasDiscount;
        $this->draftRoomCategory = $this->roomCategory;
        $this->draftRoomId = $this->roomId;
        $this->draftVeteranType = $this->veteranType;
        $this->draftBookingSource = $this->bookingSource;
        $this->draftProgramType = $this->programType;
        $this->draftProgramPaymentType = $this->programPaymentType;
        $this->draftProgramEmployerId = $this->programEmployerId;
    }

    /** @return array{sort: string, dir: string} */
    protected function resolvedBookingSort(): array
    {
        $sort = in_array($this->sort, ['id', 'check_in', 'check_out', 'nights', 'total_price', 'guests', 'created_at'], true)
            ? $this->sort
            : 'created_at';
        $dir = $this->dir === 'asc' ? 'asc' : 'desc';

        return compact('sort', 'dir');
    }
}
