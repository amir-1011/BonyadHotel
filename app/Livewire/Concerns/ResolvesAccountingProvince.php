<?php

namespace App\Livewire\Concerns;

use App\Models\Accommodation;
use App\Models\Province;

trait ResolvesAccountingProvince
{
    protected function resolveAccountingProvince(): Province
    {
        if (method_exists($this, 'accountingProvince')) {
            $province = $this->accountingProvince();

            if ($province instanceof Province) {
                return $province;
            }
        }

        throw new \RuntimeException('استان مرتبط با این عملیات مشخص نیست. لطفاً اقامتگاه یا استان را انتخاب کنید.');
    }

    protected function resolveAccountingProvinceFromAccommodation(?Accommodation $accommodation): ?Province
    {
        if (!$accommodation) {
            return null;
        }

        $accommodation->loadMissing(['city.province', 'county.province']);

        return $accommodation->resolvedProvince();
    }

    public function accountingProvinceLabel(): string
    {
        try {
            return $this->resolveAccountingProvince()->displayLabel();
        } catch (\Throwable) {
            return 'نامشخص';
        }
    }

    public function hasAccountingProvinceContext(): bool
    {
        if (!method_exists($this, 'accountingProvince')) {
            return false;
        }

        return $this->accountingProvince() instanceof Province;
    }

    protected function assertAccommodationSelectedForAccounting(): bool
    {
        if ($this->hasAccountingProvinceContext()) {
            return true;
        }

        $message = 'ابتدا اقامتگاه را انتخاب کنید تا کد حسابداری بر اساس استان اقامتگاه صادر شود.';

        if (property_exists($this, 'accommodationId')) {
            $this->addError('accommodationId', $message);
        }

        $this->dispatch('toast', type: 'warning', message: $message);

        return false;
    }
}
