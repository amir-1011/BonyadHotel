@php
$bedTypes = [
    'تخت دو‌نفره', 'دو تخت یک‌نفره', 'تخت کینگ', 'تخت کوئین',
    'تخت یک‌نفره', 'سه تخت یک‌نفره', 'سوئیت',
];
$roomAmenities = [
    'تلویزیون', 'یخچال', 'کولر گازی', 'حمام اختصاصی',
    'وان حمام', 'سشوار', 'مینی‌بار', 'بالکن', 'گاوصندوق',
    'سرویس بهداشتی فرنگی', 'Wi-Fi اختصاصی', 'بالکن رو به دریا',
];
$oldAmenities = old('amenities', $roomType?->amenities ?? []);
@endphp

{{-- Errors --}}
@if($errors->any())
<div class="alert alert-danger mb-3">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="row g-3">
    {{-- Name --}}
    <div class="col-md-6">
        <label class="form-label fw-semibold">نام اتاق <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $roomType?->name) }}" placeholder="مثلاً: اتاق استاندارد دبل">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- Bed type --}}
    <div class="col-md-6">
        <label class="form-label fw-semibold">نوع تخت</label>
        <select name="bed_type" class="form-select @error('bed_type') is-invalid @enderror">
            <option value="">— انتخاب کنید —</option>
            @foreach($bedTypes as $bt)
            <option value="{{ $bt }}" @selected(old('bed_type', $roomType?->bed_type) === $bt)>{{ $bt }}</option>
            @endforeach
        </select>
        @error('bed_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- Capacity --}}
    <div class="col-md-4">
        <label class="form-label fw-semibold">ظرفیت (نفر) <span class="text-danger">*</span></label>
        <input type="number" name="capacity" class="form-control @error('capacity') is-invalid @enderror"
               value="{{ old('capacity', $roomType?->capacity ?? 2) }}" min="1" max="20">
        @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- Size --}}
    <div class="col-md-4">
        <label class="form-label fw-semibold">متراژ (m²)</label>
        <input type="number" name="size_sqm" step="0.1" class="form-control @error('size_sqm') is-invalid @enderror"
               value="{{ old('size_sqm', $roomType?->size_sqm) }}" min="1">
        @error('size_sqm')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- Room count --}}
    <div class="col-md-4">
        <label class="form-label fw-semibold">تعداداتاق موجود از این دسته<span class="text-danger">*</span></label>
        <input type="number" name="room_count" class="form-control @error('room_count') is-invalid @enderror"
               value="{{ old('room_count', $roomType?->room_count ?? 1) }}" min="1">
        @error('room_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- Description --}}
    <div class="col-12">
        <label class="form-label fw-semibold">توضیحات</label>
        <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror"
                  placeholder="ویژگی‌های اتاق را شرح دهید...">{{ old('description', $roomType?->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    {{-- Flags --}}
    <div class="col-md-4">
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="smoking" value="1" id="smokingCheck"
                   @checked(old('smoking', $roomType?->smoking ?? false))>
            <label class="form-check-label" for="smokingCheck">اتاق سیگاری</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="has_private_bathroom" value="1" id="bathroomCheck"
                   @checked(old('has_private_bathroom', $roomType?->has_private_bathroom ?? true))>
            <label class="form-check-label" for="bathroomCheck">حمام اختصاصی</label>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="activeCheck"
                   @checked(old('is_active', $roomType?->is_active ?? true))>
            <label class="form-check-label" for="activeCheck">فعال (نمایش در سایت)</label>
        </div>
    </div>

    {{-- Sort order --}}
    <div class="col-md-3">
        <label class="form-label fw-semibold">ترتیب نمایش</label>
        <input type="number" name="sort_order" class="form-control"
               value="{{ old('sort_order', $roomType?->sort_order ?? 0) }}" min="0">
    </div>

    {{-- Amenities --}}
    <div class="col-12">
        <label class="form-label fw-semibold">امکانات اتاق</label>
        <div class="row g-2">
            @foreach($roomAmenities as $a)
            <div class="col-6 col-md-4 col-lg-3 amenity-check">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="amenities[]"
                           value="{{ $a }}" id="am_{{ $loop->index }}"
                           @checked(in_array($a, $oldAmenities))>
                    <label class="form-check-label" for="am_{{ $loop->index }}">{{ $a }}</label>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Images --}}
    <div class="col-12">
        <label class="form-label fw-semibold">تصاویر اتاق</label>

        @if($roomType && $roomType->images && count($roomType->images))
        <div class="mb-2">
            <div class="text-muted small mb-1">تصاویر فعلی (تیک بزنید تا نگه داشته شود):</div>
            <div class="d-flex flex-wrap gap-2">
                @foreach($roomType->images as $img)
                <label class="position-relative" style="cursor:pointer">
                    <input type="checkbox" name="keep_images[]" value="{{ $img }}"
                           class="position-absolute top-0 start-0 m-1"
                           style="width:18px;height:18px;cursor:pointer"
                           checked>
                    <img src="{{ asset('storage/'.$img) }}" alt="room"
                         style="width:100px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #ccc">
                </label>
                @endforeach
            </div>
        </div>
        <label class="form-label small fw-semibold mt-2">تصاویر جدید (اضافه کنید):</label>
        <input type="file" name="new_images[]" id="imagesInput" class="form-control" accept="image/*" multiple>
        @else
        <input type="file" name="images[]" id="imagesInput" class="form-control" accept="image/*" multiple>
        @endif

        <div id="newImagesPreview" class="d-flex flex-wrap gap-2 mt-2"></div>
        <div class="form-text">فرمت‌های JPG, PNG, WebP — حداکثر ۴ مگابایت هر تصویر</div>
    </div>
</div>
