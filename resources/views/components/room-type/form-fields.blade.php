{{-- Shared room type form fields (admin + host). Plain HTML form — JS init via room-type-form.js + livewire:navigated --}}
@props(['roomType' => null])

@php
    $categories = config('room_types.categories', []);
    $roomAmenities = config('room_types.amenities', []);
    $oldAmenities = old('amenities', $roomType?->amenities ?? []);
    $selectedCategory = old('bed_type', $roomType?->bed_type);
    $customCategory = $selectedCategory && !in_array($selectedCategory, $categories, true) ? $selectedCategory : null;
    $customAmenities = array_values(array_diff($oldAmenities, $roomAmenities));
@endphp

@if($errors->any())
<div class="alert alert-danger mb-3">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="row g-4" data-room-type-form>

    {{-- ── اطلاعات اصلی ─────────────────────────────────────────────── --}}
    <div class="col-12">
        <div class="rt-form-section">
            <div class="rt-form-section__title">
                <i class="bi bi-info-circle text-primary"></i>
                <span>اطلاعات اصلی</span>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">نام اتاق <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $roomType?->name) }}" placeholder="مثلاً: اتاق استاندارد دبل">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">نوع اتاق</label>
                    <select name="bed_type" data-room-category-select class="form-select @error('bed_type') is-invalid @enderror">
                        <option value="">— انتخاب کنید —</option>
                        @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected($selectedCategory === $cat)>{{ $cat }}</option>
                        @endforeach
                        @if($customCategory)
                        <option value="{{ $customCategory }}" @selected(true)>{{ $customCategory }}</option>
                        @endif
                    </select>
                    @error('bed_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="mt-1">
                        <div data-room-category-add-panel class="d-none">
                            <div class="input-group input-group-sm">
                                <input data-room-category-new-input type="text" class="form-control" placeholder="نام نوع اتاق جدید">
                                <button data-action="confirm-add-room-category" type="button" class="btn btn-success">افزودن</button>
                                <button data-action="cancel-add-room-category" type="button" class="btn btn-outline-secondary">انصراف</button>
                            </div>
                            <div data-room-category-error class="text-danger small mt-1 d-none"></div>
                        </div>
                        <button data-action="toggle-add-room-category" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                            <i class="bi bi-plus-circle me-1"></i>نوع در لیست نیست؟ افزودن
                        </button>
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">ظرفیت (نفر) <span class="text-danger">*</span></label>
                    <input type="number" name="capacity" class="form-control @error('capacity') is-invalid @enderror"
                           value="{{ old('capacity', $roomType?->capacity ?? 2) }}" min="1" max="20">
                    @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">متراژ (m²)</label>
                    <input type="number" name="size_sqm" step="0.1" class="form-control @error('size_sqm') is-invalid @enderror"
                           value="{{ old('size_sqm', $roomType?->size_sqm) }}" min="1" placeholder="مثلاً: 25">
                    @error('size_sqm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">تعداد اتاق موجود از این دسته <span class="text-danger">*</span></label>
                    <input type="number" name="room_count" class="form-control @error('room_count') is-invalid @enderror"
                           value="{{ old('room_count', $roomType?->room_count ?? 1) }}" min="1">
                    @error('room_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- ── ظرفیت اضافه ──────────────────────────────────────────────── --}}
    <div class="col-12">
        <div class="rt-form-section rt-form-section--highlight">
            <div class="rt-form-section__title">
                <i class="bi bi-person-add text-warning"></i>
                <span>ظرفیت اضافه (کف‌خوابی / تخت اضافه)</span>
            </div>
            <p class="text-muted small mb-3">اگر اتاق امکان پذیرش نفر اضافه دارد، ظرفیت و قیمت آن را وارد کنید. در غیر این صورت خالی بگذارید.</p>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">حداکثر نفرات اضافه مجاز</label>
                    <input type="number" name="extra_capacity" class="form-control @error('extra_capacity') is-invalid @enderror"
                           value="{{ old('extra_capacity', $roomType?->extra_capacity) }}" min="1" max="10" placeholder="مثلاً: 2">
                    @error('extra_capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">قیمت هر نفر اضافه / شب (تومان)</label>
                    <x-money-input name="extra_capacity_price" class="form-control @error('extra_capacity_price') is-invalid @enderror"
                           value="{{ old('extra_capacity_price', $roomType?->extra_capacity_price) }}" placeholder="مثلاً: 200,000" />
                    @error('extra_capacity_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- ── توضیحات و تنظیمات ─────────────────────────────────────────── --}}
    <div class="col-12">
        <div class="rt-form-section">
            <div class="rt-form-section__title">
                <i class="bi bi-sliders text-secondary"></i>
                <span>توضیحات و تنظیمات</span>
            </div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold">توضیحات</label>
                    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror"
                              placeholder="ویژگی‌های اتاق را شرح دهید...">{{ old('description', $roomType?->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="activeCheck"
                               @checked(old('is_active', $roomType?->is_active ?? true))>
                        <label class="form-check-label" for="activeCheck">فعال (نمایش در سایت)</label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">ترتیب نمایش</label>
                    <input type="number" name="sort_order" class="form-control"
                           value="{{ old('sort_order', $roomType?->sort_order ?? 0) }}" min="0">
                    <div class="form-text">عدد کوچک‌تر = نمایش زودتر در لیست</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── امکانات اتاق ───────────────────────────────────────────────── --}}
    <div class="col-12">
        <div class="rt-form-section">
            <div class="rt-form-section__title">
                <i class="bi bi-stars text-success"></i>
                <span>امکانات اتاق</span>
            </div>
            <div data-amenities-grid class="row g-2">
                @foreach($roomAmenities as $a)
                <div class="col-6 col-md-4 col-lg-3">
                    <label class="rt-amenity-tile">
                        <input type="checkbox" name="amenities[]" value="{{ $a }}"
                               class="rt-amenity-input" id="am_{{ $loop->index }}"
                               @checked(in_array($a, $oldAmenities, true))>
                        <span class="rt-amenity-tile__label">{{ $a }}</span>
                        <i class="bi bi-check-circle-fill rt-amenity-tile__check" aria-hidden="true"></i>
                    </label>
                </div>
                @endforeach
                @foreach($customAmenities as $a)
                <div class="col-6 col-md-4 col-lg-3">
                    <label class="rt-amenity-tile">
                        <input type="checkbox" name="amenities[]" value="{{ $a }}"
                               class="rt-amenity-input" id="am_custom_{{ $loop->index }}"
                               checked>
                        <span class="rt-amenity-tile__label">{{ $a }}</span>
                        <i class="bi bi-check-circle-fill rt-amenity-tile__check" aria-hidden="true"></i>
                    </label>
                </div>
                @endforeach
            </div>
            <div class="mt-2">
                <div data-amenity-add-panel class="d-none">
                    <div class="input-group input-group-sm" style="max-width: 420px;">
                        <input data-amenity-new-input type="text" class="form-control" placeholder="نام امکان جدید">
                        <button data-action="confirm-add-amenity" type="button" class="btn btn-success">افزودن</button>
                        <button data-action="cancel-add-amenity" type="button" class="btn btn-outline-secondary">انصراف</button>
                    </div>
                    <div data-amenity-error class="text-danger small mt-1 d-none"></div>
                </div>
                <button data-action="toggle-add-amenity" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                    <i class="bi bi-plus-circle me-1"></i>امکان در لیست نیست؟ افزودن
                </button>
            </div>
        </div>
    </div>

    {{-- ── تصاویر ─────────────────────────────────────────────────────── --}}
    <div class="col-12">
        <div class="rt-form-section">
            <div class="rt-form-section__title">
                <i class="bi bi-images text-info"></i>
                <span>تصاویر اتاق</span>
            </div>

            @if($roomType && $roomType->images && count($roomType->images))
            <div class="mb-3">
                <div class="text-muted small mb-2">تصاویر فعلی — تیک بزنید تا نگه داشته شوند:</div>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($roomType->images as $img)
                    <label class="rt-image-keep-label">
                        <input type="checkbox" name="keep_images[]" value="{{ $img }}" checked>
                        <img src="{{ asset('storage/'.$img) }}" alt="room">
                    </label>
                    @endforeach
                </div>
            </div>
            <label class="form-label small fw-semibold">تصاویر جدید (اضافه کنید)</label>
            @endif

            <input type="file" name="new_images[]" data-room-images-input class="form-control" accept="image/*" multiple>
            <div data-room-images-preview class="d-flex flex-wrap gap-2 mt-2"></div>
            <div class="form-text">فرمت‌های JPG, PNG, WebP — حداکثر ۴ مگابایت هر تصویر</div>
        </div>
    </div>

</div>

@once
@push('styles')
<style>
.rt-form-section {
    border: 1px solid var(--bs-border-color);
    border-radius: .75rem;
    padding: 1.25rem;
    background: var(--bs-body-bg);
}
.rt-form-section--highlight {
    border-color: rgba(var(--bs-warning-rgb), .35);
    background: rgba(var(--bs-warning-rgb), .06);
}
.rt-form-section__title {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-weight: 600;
    margin-bottom: 1rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid var(--bs-border-color-translucent);
}
.rt-amenity-tile {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    border: 1.5px solid var(--bs-border-color);
    border-radius: .5rem;
    padding: .65rem .85rem;
    cursor: pointer;
    user-select: none;
    transition: border-color .15s, background .15s, box-shadow .15s;
    height: 100%;
    margin: 0;
    background: var(--bs-body-bg);
}
.rt-amenity-tile:hover {
    border-color: rgba(var(--bs-success-rgb), .45);
    background: rgba(var(--bs-success-rgb), .04);
}
.rt-amenity-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.rt-amenity-tile__label {
    font-size: .9rem;
    line-height: 1.4;
    flex: 1;
}
.rt-amenity-tile__check {
    flex-shrink: 0;
    font-size: 1rem;
    color: var(--bs-success);
    opacity: 0;
    transform: scale(.85);
    transition: opacity .15s, transform .15s;
}
.rt-amenity-tile:has(.rt-amenity-input:checked) {
    border-color: var(--bs-success);
    background: rgba(var(--bs-success-rgb), .1);
    box-shadow: inset 0 0 0 1px rgba(var(--bs-success-rgb), .15);
}
.rt-amenity-tile:has(.rt-amenity-input:checked) .rt-amenity-tile__check {
    opacity: 1;
    transform: scale(1);
}
.rt-amenity-tile:has(.rt-amenity-input:checked) .rt-amenity-tile__label {
    font-weight: 600;
    color: var(--bs-success-text-emphasis, var(--bs-success));
}
.rt-image-keep-label {
    position: relative;
    cursor: pointer;
    display: inline-block;
}
.rt-image-keep-label input[type="checkbox"] {
    position: absolute;
    top: .35rem;
    inset-inline-start: .35rem;
    width: 18px;
    height: 18px;
    cursor: pointer;
    z-index: 1;
}
.rt-image-keep-label img {
    width: 100px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--bs-border-color);
}
.rt-image-keep-label:has(input:not(:checked)) img {
    opacity: .45;
    filter: grayscale(.4);
}
.rt-image-preview-thumb {
    width: 100px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--bs-border-color);
}
</style>
@endpush
@endonce
