<?php

namespace App\Livewire\Admin;

use App\Models\AccommodationType;
use App\Models\City;
use App\Models\Province;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'استان‌ها، شهرها و انواع', 'pageTitle' => 'مدیریت استان‌ها، شهرها و انواع اقامتگاه'])]
class LocationCatalogSettings extends Component
{
    public string $tab = 'provinces';

    public function deleteProvince(int $provinceId): void
    {
        $province = Province::findOrFail($provinceId);

        if ($province->cities()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'این استان دارای شهر است و قابل حذف نیست.');
            return;
        }

        $province->delete();
        $this->dispatch('toast', type: 'success', message: 'استان حذف شد.');
    }

    public function deleteCity(int $cityId): void
    {
        $city = City::findOrFail($cityId);

        if ($city->accommodations()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'این شهر در اقامتگاه‌ها استفاده شده و قابل حذف نیست.');
            return;
        }

        $city->delete();
        $this->dispatch('toast', type: 'success', message: 'شهر حذف شد.');
    }

    public function deleteType(int $typeId): void
    {
        $type = AccommodationType::findOrFail($typeId);

        if ($type->isInUse()) {
            $this->dispatch('toast', type: 'error', message: 'این نوع در اقامتگاه‌ها استفاده شده و قابل حذف نیست.');
            return;
        }

        $type->delete();
        $this->dispatch('toast', type: 'success', message: 'نوع اقامتگاه حذف شد.');
    }

    public function render()
    {
        return view('livewire.admin.location-catalog-settings', [
            'provinces'          => Province::withCount('cities')->orderBy('name')->get(),
            'cities'             => City::with('province')->withCount('accommodations')->orderBy('name')->get(),
            'accommodationTypes' => AccommodationType::orderBy('label')->get(),
        ]);
    }
}
