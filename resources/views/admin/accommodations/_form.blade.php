{{-- Shared form fields for admin accommodation create/edit --}}
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label small fw-semibold">نام اقامتگاه</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $accommodation->name ?? '') }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">نوع</label>
        <select name="type" class="form-select" required>
            @foreach(['hotel'=>'هتل','villa'=>'ویلا','apartment'=>'آپارتمان','hostel'=>'هاستل','traditional'=>'اقامتگاه سنتی'] as $v=>$l)
            <option value="{{ $v }}" {{ old('type', $accommodation->type ?? '') == $v ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">استان</label>
        <select name="province_id" id="province_id" class="form-select" required>
            <option value="">انتخاب استان</option>
            @foreach($provinces as $prov)
            <option value="{{ $prov->id }}" {{ isset($accommodation) && $accommodation->city->province_id == $prov->id ? 'selected' : '' }}>{{ $prov->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">شهر</label>
        <select name="city_id" id="city_id" class="form-select" required>
            @if(isset($accommodation))
                @foreach($provinces->firstWhere('id', $accommodation->city->province_id)?->cities ?? [] as $c)
                <option value="{{ $c->id }}" {{ $accommodation->city_id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            @else
                <option value="">ابتدا استان انتخاب کنید</option>
            @endif
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">قیمت/شب (تومان)</label>
        <input type="number" name="price_per_night" class="form-control" value="{{ old('price_per_night', $accommodation->price_per_night ?? '') }}" required min="0">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">ظرفیت</label>
        <input type="number" name="capacity" class="form-control" value="{{ old('capacity', $accommodation->capacity ?? 2) }}" required min="1">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold">اتاق‌ها</label>
        <input type="number" name="rooms" class="form-control" value="{{ old('rooms', $accommodation->rooms ?? 1) }}" required min="1">
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold">آدرس</label>
        <input type="text" name="address" class="form-control" value="{{ old('address', $accommodation->address ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">عرض جغرافیایی (lat)</label>
        <input type="number" step="any" name="lat" class="form-control" value="{{ old('lat', $accommodation->lat ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">طول جغرافیایی (lng)</label>
        <input type="number" step="any" name="lng" class="form-control" value="{{ old('lng', $accommodation->lng ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">میزبان</label>
        <select name="host_id" class="form-select">
            <option value="">بدون میزبان</option>
            @foreach($hosts as $h)
            <option value="{{ $h->id }}" {{ old('host_id', $accommodation->host_id ?? '') == $h->id ? 'selected' : '' }}>{{ $h->name ?? $h->mobile }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold">لینک تصویر</label>
        <input type="text" name="image" class="form-control" value="{{ old('image', $accommodation->image ?? '') }}" placeholder="https://...">
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold">توضیحات</label>
        <textarea name="description" class="form-control" rows="3">{{ old('description', $accommodation->description ?? '') }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold">امکانات (هر خط یک مورد)</label>
        <textarea name="amenities_raw" class="form-control" rows="5" placeholder="Wi-Fi&#10;پارکینگ&#10;استخر">{{ old('amenities_raw', implode("\n", $accommodation->amenities ?? [])) }}</textarea>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" value="1" {{ old('is_active', $accommodation->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label small" for="is_active">اقامتگاه فعال باشد</label>
        </div>
    </div>
</div>
