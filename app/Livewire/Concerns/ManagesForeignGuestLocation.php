<?php

namespace App\Livewire\Concerns;

use App\Models\ResidenceCity;
use App\Services\LocationCatalogService;
use Illuminate\Validation\Rule;

trait ManagesForeignGuestLocation
{
    public bool $showAddCountry = false;
    public bool $showAddResidenceCity = false;
    public string $newCountryName = '';
    public string $newResidenceCityName = '';

    public function updatedForeignCountryId(): void
    {
        $this->foreignResidenceCityId = 0;
    }

    public function toggleAddCountry(): void
    {
        $this->showAddCountry = !$this->showAddCountry;
        $this->newCountryName = '';
        $this->resetErrorBag('newCountryName');
    }

    public function toggleAddResidenceCity(): void
    {
        $this->showAddResidenceCity = !$this->showAddResidenceCity;
        $this->newResidenceCityName = '';
        $this->resetErrorBag('newResidenceCityName');
    }

    public function addCountry(): void
    {
        $this->validate([
            'newCountryName' => ['required', 'string', 'max:100', 'unique:countries,name'],
        ], [], ['newCountryName' => 'نام کشور']);

        $country = app(LocationCatalogService::class)->createCountry($this->newCountryName);

        $this->foreignCountryId = $country->id;
        $this->foreignResidenceCityId = 0;
        $this->showAddCountry = false;
        $this->newCountryName = '';

        $this->dispatch('toast', type: 'success', message: 'کشور اضافه شد.');
    }

    public function addResidenceCity(): void
    {
        $this->validate([
            'foreignCountryId' => ['required', 'integer', 'exists:countries,id'],
            'newResidenceCityName' => [
                'required',
                'string',
                'max:100',
                Rule::unique('residence_cities', 'name')->where(fn ($q) => $q->where('country_id', $this->foreignCountryId)),
            ],
        ], [], ['newResidenceCityName' => 'نام شهر']);

        $city = app(LocationCatalogService::class)->createResidenceCity(
            $this->foreignCountryId,
            $this->newResidenceCityName,
        );

        $this->foreignResidenceCityId = $city->id;
        $this->showAddResidenceCity = false;
        $this->newResidenceCityName = '';

        $this->dispatch('toast', type: 'success', message: 'شهر اضافه شد.');
    }

    protected function residenceCityIdRules(): array
    {
        return [
            function (string $attribute, mixed $value, \Closure $fail): void {
                $cityId = (int) $value;
                if ($cityId <= 0) {
                    return;
                }

                if (!ResidenceCity::where('id', $cityId)->where('country_id', $this->foreignCountryId)->exists()) {
                    $fail('شهر انتخاب‌شده با کشور هم‌خوانی ندارد.');
                }
            },
        ];
    }
}
