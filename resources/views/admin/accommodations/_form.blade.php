{{-- Shared form fields for admin accommodation create/edit --}}
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label small fw-semibold">نام اقامتگاه</label>
        <input type="text" wire:model="name" class="form-control" required>
        @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    @include('components.accommodation.type-field', ['accommodationTypes' => $accommodationTypes])
    @include('components.accommodation.location-fields', ['provinces' => $provinces, 'cities' => $cities])
    <div class="col-md-4 d-none">
        <label class="form-label small fw-semibold">قیمت/شب (ریال)</label>
        <x-money-input wire:model="pricePerNight" class="form-control" required min="0" />
        @error('pricePerNight') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">ظرفیت</label>
        <input type="number" wire:model="capacity" class="form-control" required min="1">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">اتاق‌ها</label>
        <input type="number" wire:model="rooms" class="form-control" required min="1">
    </div>
    @include('components.accommodation.children-under-6-policy')
    <div class="col-12">
        <label class="form-label small fw-semibold">آدرس</label>
        <input type="text" wire:model="address" class="form-control">
    </div>

    @include('components.accommodation.management-and-phones')
    <div class="col-md-6">
        <label class="form-label small fw-semibold">عرض جغرافیایی (lat)</label>
        <input type="number" step="any" wire:model="lat" class="form-control">
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">طول جغرافیایی (lng)</label>
        <input type="number" step="any" wire:model="lng" class="form-control">
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">میزبان</label>
        <select wire:model="hostId" class="form-select">
            <option value="">بدون میزبان</option>
            @foreach($hosts as $h)
            <option value="{{ $h->id }}">{{ $h->name ?? $h->mobile }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">لینک تصویر</label>
        <input type="text" wire:model="image" class="form-control" placeholder="https://...">
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold">توضیحات</label>
        <textarea wire:model="description" class="form-control" rows="3"></textarea>
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold">امکانات (با کاما جدا کنید)</label>
        <textarea wire:model="amenitiesRaw" class="form-control" rows="3" placeholder="Wi-Fi, پارکینگ, استخر"></textarea>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" wire:model="isActive" class="form-check-input" id="is_active">
            <label class="form-check-label small" for="is_active">اقامتگاه فعال باشد</label>
        </div>
    </div>
</div>
