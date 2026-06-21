<?php

namespace App\Livewire\Concerns;

use App\Models\AccommodationType;
use App\Models\Province;
use App\Services\LocationCatalogService;
use Illuminate\Validation\Rule;

trait ManagesAccommodationCatalog
{
    public bool $showAddProvince = false;
    public bool $showAddCity = false;
    public bool $showAddType = false;
    public string $newProvinceName = '';
    public string $newCityName = '';
    public string $newTypeLabel = '';

    protected function accommodationTypeRule(): array
    {
        return ['required', Rule::exists('accommodation_types', 'key')];
    }

    public function toggleAddProvince(): void
    {
        $this->showAddProvince = !$this->showAddProvince;
        $this->newProvinceName = '';
        $this->resetErrorBag('newProvinceName');
    }

    public function toggleAddCity(): void
    {
        $this->showAddCity = !$this->showAddCity;
        $this->newCityName = '';
        $this->resetErrorBag('newCityName');
    }

    public function toggleAddType(): void
    {
        $this->showAddType = !$this->showAddType;
        $this->newTypeLabel = '';
        $this->resetErrorBag('newTypeLabel');
    }

    public function addProvince(): void
    {
        $this->validate([
            'newProvinceName' => ['required', 'string', 'max:100', 'unique:provinces,name'],
        ], [], ['newProvinceName' => 'نام استان']);

        $province = app(LocationCatalogService::class)->createProvince($this->newProvinceName);

        $this->provinceId = $province->id;
        $this->cityId = 0;
        $this->showAddProvince = false;
        $this->newProvinceName = '';

        $this->dispatch('toast', type: 'success', message: 'استان اضافه شد.');
    }

    public function addCity(): void
    {
        $this->validate([
            'provinceId' => ['required', 'integer', 'exists:provinces,id'],
            'newCityName'  => [
                'required',
                'string',
                'max:100',
                Rule::unique('cities', 'name')->where(fn ($q) => $q->where('province_id', $this->provinceId)),
            ],
        ], [], ['newCityName' => 'نام شهر']);

        $location = app(LocationCatalogService::class)->resolveOrCreateCity(
            Province::findOrFail($this->provinceId)->name,
            $this->newCityName
        );

        $this->cityId = $location['id'];
        $this->showAddCity = false;
        $this->newCityName = '';

        $this->dispatch('toast', type: 'success', message: 'شهر اضافه شد.');
    }

    public function addType(): void
    {
        $this->validate([
            'newTypeLabel' => ['required', 'string', 'max:100'],
        ], [], ['newTypeLabel' => 'نام نوع']);

        $type = AccommodationType::findOrCreateByLabel($this->newTypeLabel);

        $this->type = $type->key;
        $this->showAddType = false;
        $this->newTypeLabel = '';

        $this->dispatch('toast', type: 'success', message: 'نوع اقامتگاه اضافه شد.');
    }
}
