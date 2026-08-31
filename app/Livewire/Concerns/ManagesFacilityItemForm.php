<?php

namespace App\Livewire\Concerns;

use App\Models\Province;
use App\Services\FacilityItemBrandCatalogService;
use App\Services\ImageUploadService;
use App\Services\FacilityItemCategoryCatalogService;
use App\Services\LocationCatalogService;
use App\Support\JalaliDateTimeInput;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\WithFileUploads;

trait ManagesFacilityItemForm
{
    use WithFileUploads;

    public string $formPanel = 'host';
    public int $provinceId = 0;
    public int $categoryId = 0;
    public int $brandId = 0;
    public string $name = '';
    public string $unitVolume = '';
    public int $quantity = 0;
    public string $expiryDateJalali = '';
    public string $contactPhone = '';
    public string $description = '';

    public bool $showAddProvince = false;
    public bool $showAddCategory = false;
    public bool $showAddBrand = false;
    public string $newProvinceName = '';
    public string $newCategoryName = '';
    public string $newBrandName = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $images = [];

    /** @var list<string> */
    public array $keptImagePaths = [];

    protected function mountFacilityItemFormDefaults(): void
    {
        $user = Auth::user();

        if ($user?->province_id) {
            $this->provinceId = (int) $user->province_id;
        }

        if ($user?->mobile && $this->contactPhone === '') {
            $this->contactPhone = $user->mobile;
        }
    }

    protected function loadFacilityItemFormFrom(\App\Models\FacilityExchangeItem $item): void
    {
        $this->name = $item->name;
        $this->brandId = (int) ($item->brand_id ?? 0);
        $this->categoryId = (int) $item->category_id;
        $this->unitVolume = $item->unit_volume;
        $this->quantity = (int) $item->quantity;
        $this->provinceId = (int) $item->province_id;
        $this->expiryDateJalali = $item->expiry_date
            ? \Morilog\Jalali\Jalalian::fromDateTime($item->expiry_date)->format('Y/m/d')
            : '';
        $this->contactPhone = $item->contact_phone;
        $this->description = $item->description ?? '';
        $this->images = [];
        $this->keptImagePaths = $item->imagePaths();
    }

    public function removeKeptImage(string $path): void
    {
        $this->keptImagePaths = array_values(array_filter(
            $this->keptImagePaths,
            fn (string $kept) => $kept !== $path,
        ));
    }

    public function updatedImages(): void
    {
        if ($this->images === []) {
            return;
        }

        $this->validate(
            ImageUploadService::manyFileRules('images', true),
            [],
            $this->facilityItemFormAttributes(),
        );
    }

    protected function facilityItemFormRules(bool $isSurplus, bool $imageRequired = false): array
    {
        $rules = [
            'name'           => ['required', 'string', 'max:200'],
            'brandId'        => [
                'nullable',
                'integer',
                'min:0',
                Rule::when($this->brandId > 0, ['exists:facility_item_brands,id']),
            ],
            'categoryId'     => ['required', 'integer', 'min:1', 'exists:facility_item_categories,id'],
            'unitVolume'     => ['nullable', 'string', 'max:200'],
            'quantity'       => ['nullable', 'integer', 'min:0', 'max:999999'],
            'provinceId'     => ['required', 'integer', 'exists:provinces,id'],
            'expiryDateJalali' => ['nullable', 'string', 'max:20'],
            'contactPhone'   => ['required', 'string', 'max:20', 'regex:/^(09[0-9]{9}|0[1-9][0-9]{8,10})$/'],
            'description'    => ['nullable', 'string', 'max:2000'],
        ];

        if ($isSurplus) {
            $rules = array_merge($rules, ImageUploadService::manyFileRules('images', true));
        }

        return $rules;
    }

    protected function facilityItemFormAttributes(): array
    {
        return [
            'name' => 'نام کالا',
            'brandId' => 'برند',
            'categoryId' => 'دسته‌بندی',
            'unitVolume' => 'حجم واحد',
            'quantity' => 'تعداد',
            'provinceId' => 'استان مبدا',
            'expiryDateJalali' => 'تاریخ انقضا',
            'contactPhone' => 'شماره تماس',
            'description' => 'توضیحات',
            'images' => 'عکس‌ها',
            'images.*' => 'عکس',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedFacilityItemPayload(): array
    {
        $expiryGregorian = null;

        if ($this->expiryDateJalali !== '') {
            $expiryGregorian = JalaliDateTimeInput::toGregorianDate($this->expiryDateJalali);

            if (!$expiryGregorian) {
                $this->addError('expiryDateJalali', 'تاریخ انقضا معتبر نیست. فرمت: ۱۴۰۳/۰۱/۰۱');

                throw \Illuminate\Validation\ValidationException::withMessages([
                    'expiryDateJalali' => 'تاریخ انقضا معتبر نیست. فرمت: ۱۴۰۳/۰۱/۰۱',
                ]);
            }
        }

        return [
            'name'           => trim($this->name),
            'brand_id'       => $this->brandId > 0 ? $this->brandId : null,
            'category_id'    => $this->categoryId,
            'unit_volume'    => trim($this->unitVolume),
            'quantity'       => $this->quantity > 0 ? $this->quantity : 1,
            'province_id'    => $this->provinceId,
            'expiry_date'    => $expiryGregorian,
            'contact_phone'  => trim($this->contactPhone),
            'description'    => trim($this->description) !== '' ? trim($this->description) : null,
        ];
    }

    public function toggleAddProvince(): void
    {
        $this->showAddProvince = !$this->showAddProvince;
        $this->newProvinceName = '';
        $this->resetErrorBag('newProvinceName');
    }

    public function toggleAddCategory(): void
    {
        $this->showAddCategory = !$this->showAddCategory;
        $this->newCategoryName = '';
        $this->resetErrorBag('newCategoryName');
    }

    public function toggleAddBrand(): void
    {
        $this->showAddBrand = !$this->showAddBrand;
        $this->newBrandName = '';
        $this->resetErrorBag('newBrandName');
    }

    public function addProvince(): void
    {
        $this->validate([
            'newProvinceName' => ['required', 'string', 'max:100', 'unique:provinces,name'],
        ], [], ['newProvinceName' => 'نام استان']);

        $province = app(LocationCatalogService::class)->createProvince($this->newProvinceName);

        $this->provinceId = $province->id;
        $this->showAddProvince = false;
        $this->newProvinceName = '';

        $this->dispatch('toast', type: 'success', message: 'استان اضافه شد.');
    }

    public function addCategory(): void
    {
        $this->validate([
            'newCategoryName' => ['required', 'string', 'max:100'],
        ], [], ['newCategoryName' => 'نام دسته‌بندی']);

        $category = app(FacilityItemCategoryCatalogService::class)->add($this->newCategoryName);

        $this->categoryId = $category->id;
        $this->showAddCategory = false;
        $this->newCategoryName = '';

        $this->dispatch('toast', type: 'success', message: 'دسته‌بندی اضافه شد.');
    }

    public function addBrand(): void
    {
        $this->validate([
            'newBrandName' => ['required', 'string', 'max:100'],
        ], [], ['newBrandName' => 'نام برند']);

        $brand = app(FacilityItemBrandCatalogService::class)->add(
            $this->newBrandName,
            Auth::id(),
        );

        $this->brandId = $brand->id;
        $this->showAddBrand = false;
        $this->newBrandName = '';

        $this->dispatch('toast', type: 'success', message: 'برند اضافه شد.');
    }

    protected function facilityItemFormCancelRoute(bool $isSurplus): string
    {
        if ($this->formPanel === 'admin') {
            return $isSurplus
                ? route('admin.facility.surplus.index')
                : route('admin.facility.needed.index');
        }

        return $isSurplus
            ? route('host.facility.surplus.index')
            : route('host.facility.needed.index');
    }

    /**
     * @return array{
     *     provinces: \Illuminate\Support\Collection,
     *     categories: \Illuminate\Support\Collection,
     *     brands: \Illuminate\Support\Collection
     * }
     */
    protected function facilityItemFormViewData(): array
    {
        return [
            'provinces'  => Province::orderBy('name')->get(),
            'categories' => app(FacilityItemCategoryCatalogService::class)->allOrdered(),
            'brands'     => app(FacilityItemBrandCatalogService::class)->allOrdered(),
        ];
    }
}
