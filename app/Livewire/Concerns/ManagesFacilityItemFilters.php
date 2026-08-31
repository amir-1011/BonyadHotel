<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

trait ManagesFacilityItemFilters
{
    #[Url] public string $search = '';
    #[Url] public int $categoryId = 0;
    #[Url] public int $provinceId = 0;
    #[Url] public string $mineOnly = '';

    public string $filterSearch = '';
    public int $filterCategoryId = 0;
    public int $filterProvinceId = 0;
    public string $filterMineOnly = '';

    public function mountFacilityFilters(): void
    {
        $this->syncFilterFormFromApplied();
    }

    public function applyFilters(): void
    {
        $this->search = $this->filterSearch;
        $this->categoryId = $this->filterCategoryId;
        $this->provinceId = $this->filterProvinceId;
        $this->mineOnly = $this->filterMineOnly;
        $this->resetPage();
    }

    protected function syncFilterFormFromApplied(): void
    {
        $this->filterSearch = $this->search;
        $this->filterCategoryId = $this->categoryId;
        $this->filterProvinceId = $this->provinceId;
        $this->filterMineOnly = $this->mineOnly;
    }

    protected function supportsMineOnlyFilter(): bool
    {
        return true;
    }
}
