<?php

namespace App\Livewire\Concerns;

use App\Models\AccommodationType;
use App\Models\County;
use App\Models\Province;
use App\Services\LocationCatalogService;
use Illuminate\Validation\Rule;

trait ManagesAccommodationCatalog
{
    public bool $showAddProvince = false;
    public bool $showAddCity = false;
    public bool $showAddCounty = false;
    public bool $showAddType = false;
    public string $newProvinceName = '';
    public string $newCityName = '';
    public string $newCountyName = '';
    public string $newTypeLabel = '';

    protected function accommodationTypeRule(): array
    {
        return ['required', Rule::exists('accommodation_types', 'key')];
    }

    protected function countyIdRules(): array
    {
        return [
            function (string $attribute, mixed $value, \Closure $fail): void {
                $countyId = (int) $value;
                if ($countyId <= 0) {
                    return;
                }

                if (!County::where('id', $countyId)->where('province_id', $this->provinceId)->exists()) {
                    $fail('شهرستان انتخاب‌شده با استان هم‌خوانی ندارد.');
                }
            },
        ];
    }

    protected function normalizedCountyId(): ?int
    {
        return $this->countyId > 0 ? $this->countyId : null;
    }

    public function updatedProvinceId(): void
    {
        $this->cityId = 0;
        $this->countyId = 0;
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

    public function toggleAddCounty(): void
    {
        $this->showAddCounty = !$this->showAddCounty;
        $this->newCountyName = '';
        $this->resetErrorBag('newCountyName');
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
        $this->countyId = 0;
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

    public function addCounty(): void
    {
        $this->validate([
            'provinceId' => ['required', 'integer', 'exists:provinces,id'],
            'newCountyName' => [
                'required',
                'string',
                'max:100',
                Rule::unique('counties', 'name')->where(fn ($q) => $q->where('province_id', $this->provinceId)),
            ],
        ], [], ['newCountyName' => 'نام شهرستان']);

        $county = app(LocationCatalogService::class)->createCounty(
            $this->provinceId,
            $this->newCountyName
        );

        $this->countyId = $county->id;
        $this->showAddCounty = false;
        $this->newCountyName = '';

        $this->dispatch('toast', type: 'success', message: 'شهرستان اضافه شد.');
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
