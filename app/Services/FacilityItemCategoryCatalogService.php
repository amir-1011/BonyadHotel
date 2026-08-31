<?php

namespace App\Services;

use App\Models\FacilityItemCategory;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FacilityItemCategoryCatalogService
{
    /** @var list<string> */
    public const DEFAULT_NAMES = [
        'تاسیسات',
        'مواد مصرفی',
        'تجهیزات',
    ];

    public function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', '‌'], ['ی', 'ک', ' '], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public function ensureSeeded(): void
    {
        if (FacilityItemCategory::query()->exists()) {
            return;
        }

        foreach (self::DEFAULT_NAMES as $index => $name) {
            FacilityItemCategory::query()->create([
                'name'       => $name,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @return Collection<int, FacilityItemCategory>
     */
    public function allOrdered(): Collection
    {
        $this->ensureSeeded();

        return FacilityItemCategory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function add(string $name): FacilityItemCategory
    {
        $this->ensureSeeded();

        $name = $this->normalize($name);

        if ($name === '') {
            throw ValidationException::withMessages(['newCategoryName' => 'نام دسته‌بندی را وارد کنید.']);
        }

        if (mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['newCategoryName' => 'نام دسته‌بندی نباید بیشتر از ۱۰۰ کاراکتر باشد.']);
        }

        $existing = FacilityItemCategory::query()->where('name', $name)->first();

        if ($existing) {
            return $existing;
        }

        $maxSort = (int) FacilityItemCategory::query()->max('sort_order');

        return FacilityItemCategory::query()->create([
            'name'       => $name,
            'sort_order' => $maxSort + 1,
        ]);
    }

    public function update(int $id, string $name): FacilityItemCategory
    {
        $this->ensureSeeded();

        $name = $this->normalize($name);

        if ($name === '') {
            throw ValidationException::withMessages(['editCategoryName' => 'نام دسته‌بندی را وارد کنید.']);
        }

        if (mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['editCategoryName' => 'نام دسته‌بندی نباید بیشتر از ۱۰۰ کاراکتر باشد.']);
        }

        $category = FacilityItemCategory::query()->findOrFail($id);
        $duplicate = FacilityItemCategory::query()
            ->where('name', $name)
            ->whereKeyNot($category->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['editCategoryName' => 'این دسته‌بندی قبلاً ثبت شده است.']);
        }

        $category->update(['name' => $name]);

        return $category->fresh();
    }

    public function delete(int $id): void
    {
        $this->ensureSeeded();

        $category = FacilityItemCategory::query()->findOrFail($id);

        if ($category->items()->exists()) {
            throw ValidationException::withMessages([
                'editCategoryName' => 'این دسته‌بندی در موارد ثبت‌شده استفاده شده و قابل حذف نیست.',
            ]);
        }

        $category->delete();
    }
}
