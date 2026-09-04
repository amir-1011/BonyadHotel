<?php

namespace App\Livewire\Concerns;

use App\Models\Accommodation;
use App\Models\Province;

trait ResolvesAccountingProvince
{
    public ?int $accountingProvinceId = null;

    public bool $accountingProvinceManuallySet = false;

    protected function resolveAccountingProvince(): Province
    {
        if ($this->accountingProvinceId) {
            return Province::query()->findOrFail($this->accountingProvinceId);
        }

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

    protected function syncDefaultAccountingProvinceFromContext(): void
    {
        if ($this->accountingProvinceManuallySet) {
            return;
        }

        $province = null;

        if (method_exists($this, 'accountingProvince')) {
            $province = $this->accountingProvince();
        }

        $this->accountingProvinceId = $province?->id;
    }

    public function updatedAccountingProvinceId(): void
    {
        $this->accountingProvinceManuallySet = true;
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
        if ($this->accountingProvinceId) {
            return true;
        }

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

        $message = 'ابتدا اقامتگاه را انتخاب کنید تا استان پیش‌فرض کد حسابداری تعیین شود، یا استان را دستی انتخاب کنید.';

        if (property_exists($this, 'accommodationId')) {
            $this->addError('accommodationId', $message);
        }

        if (property_exists($this, 'accountingProvinceId')) {
            $this->addError('accountingProvinceId', $message);
        }

        $this->dispatch('toast', type: 'warning', message: $message);

        return false;
    }

    protected function requiresAccommodationContextForCatalog(): bool
    {
        return true;
    }
}
