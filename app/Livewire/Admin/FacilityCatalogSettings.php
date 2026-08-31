<?php

namespace App\Livewire\Admin;

use App\Models\FacilityItemBrand;
use App\Models\FacilityItemCategory;
use App\Services\FacilityItemBrandCatalogService;
use App\Services\FacilityItemCategoryCatalogService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'دسته‌بندی و برند', 'pageTitle' => 'مدیریت دسته‌بندی و برند'])]
class FacilityCatalogSettings extends Component
{
    public string $tab = 'categories';

    /** @var array<int, string> */
    public array $categoryNames = [];

    /** @var array<int, string> */
    public array $brandNames = [];

    public function mount(): void
    {
        $this->syncNames();
    }

    public function updatedTab(): void
    {
        $this->syncNames();
    }

    public function saveCategories(): void
    {
        $this->validate([
            'categoryNames' => ['required', 'array'],
            'categoryNames.*' => ['required', 'string', 'max:100'],
        ], [], ['categoryNames.*' => 'نام دسته‌بندی']);

        $service = app(FacilityItemCategoryCatalogService::class);

        foreach ($this->categoryNames as $id => $name) {
            $service->update((int) $id, $name);
        }

        $this->dispatch('toast', type: 'success', message: 'دسته‌بندی‌ها به‌روزرسانی شد.');
        $this->syncNames();
    }

    public function saveBrands(): void
    {
        $this->validate([
            'brandNames' => ['required', 'array'],
            'brandNames.*' => ['required', 'string', 'max:100'],
        ], [], ['brandNames.*' => 'نام برند']);

        $service = app(FacilityItemBrandCatalogService::class);

        foreach ($this->brandNames as $id => $name) {
            $service->update((int) $id, $name);
        }

        $this->dispatch('toast', type: 'success', message: 'برندها به‌روزرسانی شد.');
        $this->syncNames();
    }

    public function deleteCategory(int $id): void
    {
        try {
            app(FacilityItemCategoryCatalogService::class)->delete($id);
            $this->dispatch('toast', type: 'success', message: 'دسته‌بندی حذف شد.');
            $this->syncNames();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('categoryNames.' . $id, $e->validator->errors()->first());
        }
    }

    public function deleteBrand(int $id): void
    {
        try {
            app(FacilityItemBrandCatalogService::class)->delete($id);
            $this->dispatch('toast', type: 'success', message: 'برند حذف شد.');
            $this->syncNames();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->addError('brandNames.' . $id, $e->validator->errors()->first());
        }
    }

    private function syncNames(): void
    {
        $this->categoryNames = app(FacilityItemCategoryCatalogService::class)
            ->allOrdered()
            ->mapWithKeys(fn (FacilityItemCategory $category) => [$category->id => $category->name])
            ->all();

        $this->brandNames = app(FacilityItemBrandCatalogService::class)
            ->allOrdered()
            ->mapWithKeys(fn (FacilityItemBrand $brand) => [$brand->id => $brand->name])
            ->all();
    }

    public function render()
    {
        $categories = FacilityItemCategory::query()
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $brands = FacilityItemBrand::query()
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('livewire.admin.facility-catalog-settings', [
            'categories' => $categories,
            'brands' => $brands,
        ]);
    }
}
