<?php

namespace App\Livewire\Admin;

use App\Models\AccommodationType;
use App\Models\City;
use App\Models\County;
use App\Models\Province;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'استان‌ها، شهرها و انواع', 'pageTitle' => 'مدیریت استان‌ها، شهرها و انواع اقامتگاه'])]
class LocationCatalogSettings extends Component
{
    public string $tab = 'provinces';

    /** @var array<int, string> */
    public array $provinceAccountingCodes = [];

    public function mount(): void
    {
        $this->syncProvinceAccountingCodes();
    }

    public function updatedTab(): void
    {
        if ($this->tab === 'provinces') {
            $this->syncProvinceAccountingCodes();
        }
    }

    public function saveProvinceAccountingCodes(): void
    {
        $this->validate([
            'provinceAccountingCodes'   => ['required', 'array'],
            'provinceAccountingCodes.*' => ['nullable', 'digits:3', 'distinct'],
        ], [], [
            'provinceAccountingCodes.*' => 'کد حسابداری استان',
        ]);

        foreach ($this->provinceAccountingCodes as $provinceId => $code) {
            $province = Province::query()->find($provinceId);

            if (!$province) {
                continue;
            }

            $normalized = trim((string) $code);

            if ($normalized === '') {
                $province->update(['accounting_code' => null]);
                continue;
            }

            $duplicate = Province::query()
                ->where('accounting_code', $normalized)
                ->whereKeyNot($province->id)
                ->exists();

            if ($duplicate) {
                $this->addError("provinceAccountingCodes.{$provinceId}", 'این کد قبلاً برای استان دیگری ثبت شده است.');

                return;
            }

            $province->update(['accounting_code' => $normalized]);
        }

        $this->dispatch('toast', type: 'success', message: 'کدهای حسابداری استان‌ها ذخیره شد.');
        $this->syncProvinceAccountingCodes();
    }

    private function syncProvinceAccountingCodes(): void
    {
        $this->provinceAccountingCodes = Province::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Province $province) => [$province->id => (string) ($province->accounting_code ?? '')])
            ->all();
    }

    public function deleteProvince(int $provinceId): void
    {
        $province = Province::findOrFail($provinceId);

        if ($province->cities()->exists() || $province->counties()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'این استان دارای شهر یا شهرستان است و قابل حذف نیست.');
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

    public function deleteCounty(int $countyId): void
    {
        $county = County::findOrFail($countyId);

        if ($county->accommodations()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'این شهرستان در اقامتگاه‌ها استفاده شده و قابل حذف نیست.');
            return;
        }

        $county->delete();
        $this->dispatch('toast', type: 'success', message: 'شهرستان حذف شد.');
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
            'provinces'          => Province::withCount(['cities', 'counties'])->orderBy('name')->get(),
            'cities'             => City::with('province')->withCount('accommodations')->orderBy('name')->get(),
            'counties'           => County::with('province')->withCount('accommodations')->orderBy('name')->get(),
            'accommodationTypes' => AccommodationType::orderBy('label')->get(),
        ]);
    }
}
