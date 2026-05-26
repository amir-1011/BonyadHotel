{{-- Shared form fields for admin accommodation create/edit --}}
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label small fw-semibold">نام اقامتگاه</label>
        <input type="text" wire:model="name" class="form-control" required>
        @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">نوع</label>
        <select wire:model="type" class="form-select" required>
            @foreach(['hotel'=>'هتل','villa'=>'ویلا','apartment'=>'آپارتمان','hostel'=>'هاستل','traditional'=>'اقامتگاه سنتی'] as $v=>$l)
            <option value="{{ $v }}">{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">استان</label>
        <select wire:model.live="provinceId" class="form-select">
            <option value="0">انتخاب استان</option>
            @foreach($provinces as $prov)
            <option value="{{ $prov->id }}">{{ $prov->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">شهر</label>
        <select wire:model="cityId" class="form-select" required>
            <option value="0">ابتدا استان انتخاب کنید</option>
            @foreach($cities as $c)
            <option value="{{ $c->id }}">{{ $c->name }}</option>
            @endforeach
        </select>
        @error('cityId') <div class="text-danger small">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">قیمت/شب (ریال)</label>
        <input type="number" wire:model="pricePerNight" class="form-control" required min="0">
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
    <div class="col-12">
        <label class="form-label small fw-semibold">آدرس</label>
        <input type="text" wire:model="address" class="form-control">
    </div>
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
