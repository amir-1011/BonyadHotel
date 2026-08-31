<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label small fw-semibold">نام کالا</label>
        <input wire:model="name" type="text" class="form-control @error('name') is-invalid @enderror" placeholder="مثال: گالن آب">
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label small fw-semibold">برند <span class="text-muted fw-normal">(اختیاری)</span></label>
        <select wire:model.live="brandId" class="form-select @error('brandId') is-invalid @enderror">
            <option value="0">انتخاب برند</option>
            @foreach($brands as $brand)
                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
            @endforeach
        </select>
        @error('brandId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="mt-1">
            @if($showAddBrand)
                <div class="input-group input-group-sm">
                    <input wire:model="newBrandName" type="text" class="form-control" placeholder="نام برند جدید">
                    <button wire:click="addBrand" type="button" class="btn btn-success">افزودن</button>
                    <button wire:click="toggleAddBrand" type="button" class="btn btn-outline-secondary">انصراف</button>
                </div>
                @error('newBrandName')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @else
                <button wire:click="toggleAddBrand" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                    <i class="bi bi-plus-circle me-1"></i>برند در لیست نیست؟ افزودن
                </button>
            @endif
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label small fw-semibold">دسته‌بندی</label>
        <select wire:model.live="categoryId" class="form-select @error('categoryId') is-invalid @enderror">
            <option value="0">انتخاب دسته‌بندی</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
        @error('categoryId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="mt-1">
            @if($showAddCategory)
                <div class="input-group input-group-sm">
                    <input wire:model="newCategoryName" type="text" class="form-control" placeholder="نام دسته‌بندی جدید">
                    <button wire:click="addCategory" type="button" class="btn btn-success">افزودن</button>
                    <button wire:click="toggleAddCategory" type="button" class="btn btn-outline-secondary">انصراف</button>
                </div>
                @error('newCategoryName')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @else
                <button wire:click="toggleAddCategory" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                    <i class="bi bi-plus-circle me-1"></i>دسته‌بندی در لیست نیست؟ افزودن
                </button>
            @endif
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label small fw-semibold">حجم واحد <span class="text-muted fw-normal">(اختیاری)</span></label>
        <input wire:model="unitVolume" type="text" class="form-control @error('unitVolume') is-invalid @enderror" placeholder="مثال: دو گالن ده لیتری">
        @error('unitVolume')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label small fw-semibold">تعداد <span class="text-muted fw-normal">(اختیاری)</span></label>
        <input wire:model="quantity" type="number" min="0" class="form-control @error('quantity') is-invalid @enderror" placeholder="پیش‌فرض: ۱">
        @error('quantity')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-8">
        <label class="form-label small fw-semibold">مبدا (استان)</label>
        <select wire:model.live="provinceId" class="form-select @error('provinceId') is-invalid @enderror">
            <option value="0">انتخاب استان</option>
            @foreach($provinces as $prov)
                <option value="{{ $prov->id }}">{{ $prov->name }}</option>
            @endforeach
        </select>
        @error('provinceId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="mt-1">
            @if($showAddProvince)
                <div class="input-group input-group-sm">
                    <input wire:model="newProvinceName" type="text" class="form-control" placeholder="نام استان جدید">
                    <button wire:click="addProvince" type="button" class="btn btn-success">افزودن</button>
                    <button wire:click="toggleAddProvince" type="button" class="btn btn-outline-secondary">انصراف</button>
                </div>
                @error('newProvinceName')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @else
                <button wire:click="toggleAddProvince" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                    <i class="bi bi-plus-circle me-1"></i>استان در لیست نیست؟ افزودن
                </button>
            @endif
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label small fw-semibold">تاریخ انقضا <span class="text-muted fw-normal">(در صورت وجود)</span></label>
        <input wire:model="expiryDateJalali" type="text" class="form-control @error('expiryDateJalali') is-invalid @enderror" placeholder="۱۴۰۳/۰۶/۱۵" dir="ltr">
        @error('expiryDateJalali')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label small fw-semibold">شماره تماس جهت هماهنگی</label>
        <input wire:model="contactPhone" type="text" class="form-control @error('contactPhone') is-invalid @enderror" placeholder="09123456789" dir="ltr">
        @error('contactPhone')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    @include('components.facility._item-form-images', ['showImage' => $showImage ?? false])

    <div class="col-12">
        <label class="form-label small fw-semibold">توضیحات</label>
        <textarea wire:model="description" rows="4" class="form-control @error('description') is-invalid @enderror" placeholder="توضیحات تکمیلی…"></textarea>
        @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 d-flex gap-2 justify-content-end">
        <a wire:navigate href="{{ $cancelRoute ?? (($showImage ?? false) ? route('host.facility.surplus.index') : route('host.facility.needed.index')) }}" class="btn btn-outline-secondary">انصراف</a>
        <button wire:click="{{ $submitAction ?? 'store' }}" type="button" class="btn btn-success" wire:loading.attr="disabled" wire:target="{{ ($submitAction ?? 'store') }},images">
            <span wire:loading.remove wire:target="{{ ($submitAction ?? 'store') }},images">{{ $submitLabel ?? 'ثبت' }}</span>
            <span wire:loading wire:target="{{ ($submitAction ?? 'store') }},images">در حال ثبت…</span>
        </button>
    </div>
</div>
