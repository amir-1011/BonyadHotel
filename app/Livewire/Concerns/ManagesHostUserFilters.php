<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;
use Livewire\WithPagination;

trait ManagesHostUserFilters
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'user_type')]
    public string $userType = '';

    #[Url(as: 'province_id')]
    public string $provinceId = '';

    #[Url(as: 'veteran_type')]
    public string $veteranType = '';

    #[Url(as: 'accommodation_id')]
    public string $accommodationId = '';

    #[Url(as: 'bookings_min')]
    public string $bookingsMin = '';

    #[Url(as: 'has_bookings')]
    public string $hasBookings = '';

    #[Url(as: 'sort')]
    public string $sort = '';

    public string $draftSearch = '';

    public string $draftUserType = '';

    public string $draftProvinceId = '';

    public string $draftVeteranType = '';

    public string $draftAccommodationId = '';

    public string $draftBookingsMin = '';

    public string $draftHasBookings = '';

    public string $draftSort = '';

    public function mountHostUserFilters(): void
    {
        $this->syncHostUserDraftFromApplied();
    }

    public function applyUserFilters(): void
    {
        $this->search = trim($this->draftSearch);
        $this->userType = $this->draftUserType;
        $this->provinceId = $this->draftProvinceId;
        $this->veteranType = $this->draftVeteranType;
        $this->accommodationId = $this->draftAccommodationId;
        $this->bookingsMin = $this->draftBookingsMin;
        $this->hasBookings = $this->draftHasBookings;
        $this->sort = $this->draftSort;

        $this->resetPage();
    }

    public function resetUserFilters(): void
    {
        $this->reset([
            'search',
            'userType',
            'provinceId',
            'veteranType',
            'accommodationId',
            'bookingsMin',
            'hasBookings',
            'sort',
        ]);
        $this->syncHostUserDraftFromApplied();
        $this->resetPage();
    }

    /** @return array<string, mixed> */
    protected function hostUserFilterParams(): array
    {
        return [
            'search'           => $this->search,
            'user_type'        => $this->userType,
            'province_id'      => $this->provinceId,
            'veteran_type'     => $this->veteranType,
            'accommodation_id' => $this->accommodationId,
            'bookings_min'     => $this->bookingsMin,
            'has_bookings'     => $this->hasBookings,
            'sort'             => $this->sort,
        ];
    }

    protected function syncHostUserDraftFromApplied(): void
    {
        $this->draftSearch = $this->search;
        $this->draftUserType = $this->userType;
        $this->draftProvinceId = $this->provinceId;
        $this->draftVeteranType = $this->veteranType;
        $this->draftAccommodationId = $this->accommodationId;
        $this->draftBookingsMin = $this->bookingsMin;
        $this->draftHasBookings = $this->hasBookings;
        $this->draftSort = $this->sort;
    }
}
