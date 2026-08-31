<?php

namespace App\Services;

use App\Models\FacilityItemBrand;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FacilityItemBrandCatalogService
{
    public function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', '‌'], ['ی', 'ک', ' '], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return Collection<int, FacilityItemBrand>
     */
    public function allOrdered(): Collection
    {
        return FacilityItemBrand::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function add(string $name, ?int $createdBy = null): FacilityItemBrand
    {
        $name = $this->normalize($name);

        if ($name === '') {
            throw ValidationException::withMessages(['newBrandName' => 'نام برند را وارد کنید.']);
        }

        if (mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['newBrandName' => 'نام برند نباید بیشتر از ۱۰۰ کاراکتر باشد.']);
        }

        $existing = FacilityItemBrand::query()->where('name', $name)->first();

        if ($existing) {
            return $existing;
        }

        $maxSort = (int) FacilityItemBrand::query()->max('sort_order');

        return FacilityItemBrand::query()->create([
            'name'       => $name,
            'sort_order' => $maxSort + 1,
            'created_by' => $createdBy,
        ]);
    }

    public function update(int $id, string $name): FacilityItemBrand
    {
        $name = $this->normalize($name);

        if ($name === '') {
            throw ValidationException::withMessages(['editBrandName' => 'نام برند را وارد کنید.']);
        }

        if (mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['editBrandName' => 'نام برند نباید بیشتر از ۱۰۰ کاراکتر باشد.']);
        }

        $brand = FacilityItemBrand::query()->findOrFail($id);
        $duplicate = FacilityItemBrand::query()
            ->where('name', $name)
            ->whereKeyNot($brand->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['editBrandName' => 'این برند قبلاً ثبت شده است.']);
        }

        $brand->update(['name' => $name]);

        return $brand->fresh();
    }

    public function delete(int $id): void
    {
        $brand = FacilityItemBrand::query()->findOrFail($id);

        if ($brand->items()->exists()) {
            throw ValidationException::withMessages([
                'editBrandName' => 'این برند در موارد ثبت‌شده استفاده شده و قابل حذف نیست.',
            ]);
        }

        $brand->delete();
    }
}
