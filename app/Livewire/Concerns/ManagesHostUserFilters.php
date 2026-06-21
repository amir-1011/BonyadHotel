<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;
use Livewire\WithPagination;

trait ManagesHostUserFilters
{
    use WithPagination;

    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'veteran_type')]
    public string $veteranType = '';

    #[Url(as: 'accommodation_id')]
    public string $accommodationId = '';

    #[Url(as: 'bookings_min')]
    public string $bookingsMin = '';

    public string $draftSearch = '';

    public string $draftVeteranType = '';

    public string $draftAccommodationId = '';

    public string $draftBookingsMin = '';

    public function mountHostUserFilters(): void
    {
        $this->syncHostUserDraftFromApplied();
    }

    public function applyUserFilters(): void
    {
        $this->search = trim($this->draftSearch);
        $this->veteranType = $this->draftVeteranType;
        $this->accommodationId = $this->draftAccommodationId;
        $this->bookingsMin = $this->draftBookingsMin;

        $this->resetPage();
    }

    public function resetUserFilters(): void
    {
        $this->reset(['search', 'veteranType', 'accommodationId', 'bookingsMin']);
        $this->syncHostUserDraftFromApplied();
        $this->resetPage();
    }

    /** @return array<string, mixed> */
    protected function hostUserFilterParams(): array
    {
        return [
            'search'           => $this->search,
            'veteran_type'     => $this->veteranType,
            'accommodation_id' => $this->accommodationId,
            'bookings_min'     => $this->bookingsMin,
        ];
    }

    protected function syncHostUserDraftFromApplied(): void
    {
        $this->draftSearch = $this->search;
        $this->draftVeteranType = $this->veteranType;
        $this->draftAccommodationId = $this->accommodationId;
        $this->draftBookingsMin = $this->bookingsMin;
    }
}
