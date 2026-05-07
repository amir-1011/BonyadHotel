@extends('layouts.host')
@section('title', 'اقامتگاه جدید')
@section('page-title', 'اقامتگاه جدید')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('host.accommodations.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">ثبت اقامتگاه جدید</h5>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('host.accommodations.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label small fw-semibold">نام اقامتگاه</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">نوع</label>
                    <select name="type" class="form-select" required>
                        @foreach(['hotel'=>'هتل','villa'=>'ویلا','apartment'=>'آپارتمان','hostel'=>'هاستل','traditional'=>'اقامتگاه سنتی'] as $v=>$l)
                        <option value="{{ $v }}" {{ old('type') == $v ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">استان</label>
                    <select name="province_id" id="province_id" class="form-select" required>
                        <option value="">انتخاب کنید</option>
                        @foreach($provinces as $prov)
                        <option value="{{ $prov->id }}">{{ $prov->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">شهر</label>
                    <select name="city_id" id="city_id" class="form-select" required>
                        <option value="">ابتدا استان انتخاب کنید</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">قیمت/شب (تومان)</label>
                    <input type="number" name="price_per_night" class="form-control" value="{{ old('price_per_night') }}" required min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">ظرفیت (نفر)</label>
                    <input type="number" name="capacity" class="form-control" value="{{ old('capacity', 2) }}" required min="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">اتاق‌ها</label>
                    <input type="number" name="rooms" class="form-control" value="{{ old('rooms', 1) }}" required min="1">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">آدرس</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address') }}">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">توضیحات</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">امکانات (هر خط یک مورد)</label>
                    <textarea name="amenities_raw" class="form-control" rows="4" placeholder="Wi-Fi&#10;پارکینگ&#10;استخر">{{ old('amenities_raw') }}</textarea>
                </div>

                {{-- Map Picker --}}
                <div class="col-12">
                    <label class="form-label small fw-semibold"><i class="bi bi-geo-alt me-1"></i>موقعیت روی نقشه <span class="text-muted fw-normal">(اختیاری — روی نقشه کلیک کنید)</span></label>
                    <div id="map-picker" style="height:320px;border-radius:10px;border:1px solid #dee2e6;"></div>
                    <div class="d-flex gap-3 mt-2">
                        <div class="flex-grow-1">
                            <input type="number" step="any" name="lat" id="lat" class="form-control form-control-sm" placeholder="عرض جغرافیایی" value="{{ old('lat') }}">
                        </div>
                        <div class="flex-grow-1">
                            <input type="number" step="any" name="lng" id="lng" class="form-control form-control-sm" placeholder="طول جغرافیایی" value="{{ old('lng') }}">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearMarker()">
                            <i class="bi bi-x-circle me-1"></i>پاک‌کردن
                        </button>
                    </div>
                    <div id="map-hint" class="text-muted small mt-1">برای تعیین موقعیت، روی نقشه کلیک کنید.</div>
                </div>

                {{-- Image Upload --}}
                <div class="col-12">
                    <label class="form-label small fw-semibold"><i class="bi bi-images me-1"></i>تصاویر اقامتگاه <span class="text-muted fw-normal">(حداکثر ۸ عکس، هر کدام تا ۴ مگابایت)</span></label>
                    <input type="file" name="images[]" id="image-input" class="form-control" accept="image/*" multiple>
                    <div id="image-preview" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>

                <div class="col-12">
                    <div class="alert alert-info small py-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>اقامتگاه شما پس از بررسی و تأیید مدیر نمایش داده خواهد شد.
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success px-4">ثبت اقامتگاه</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ── Province → City AJAX ──────────────────────────────────────────────────────
document.getElementById('province_id')?.addEventListener('change', function(){
    fetch(`/api/provinces/${this.value}/cities`).then(r=>r.json()).then(cities=>{
        var sel = document.getElementById('city_id');
        sel.innerHTML = cities.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
    });
});

// ── Leaflet Map Picker ────────────────────────────────────────────────────────
var initLat = parseFloat(document.getElementById('lat').value) || 32.4279;
var initLng = parseFloat(document.getElementById('lng').value) || 53.6880;
var hasInit = document.getElementById('lat').value !== '';

var map = L.map('map-picker').setView([initLat, initLng], hasInit ? 13 : 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
}).addTo(map);

var marker = null;
if (hasInit) {
    marker = L.marker([initLat, initLng], {draggable: true}).addTo(map);
    marker.on('dragend', updateFromMarker);
    updateHint(initLat, initLng);
}

map.on('click', function(e) {
    var lat = e.latlng.lat.toFixed(6);
    var lng = e.latlng.lng.toFixed(6);
    document.getElementById('lat').value = lat;
    document.getElementById('lng').value = lng;
    if (marker) { marker.setLatLng(e.latlng); }
    else {
        marker = L.marker(e.latlng, {draggable: true}).addTo(map);
        marker.on('dragend', updateFromMarker);
    }
    updateHint(lat, lng);
});

function updateFromMarker(e) {
    var pos = e.target.getLatLng();
    document.getElementById('lat').value = pos.lat.toFixed(6);
    document.getElementById('lng').value = pos.lng.toFixed(6);
    updateHint(pos.lat.toFixed(6), pos.lng.toFixed(6));
}
function updateHint(lat, lng) {
    document.getElementById('map-hint').innerHTML =
        '<i class="bi bi-check-circle-fill text-success me-1"></i>موقعیت انتخاب شد: ' + lat + '، ' + lng;
}
function clearMarker() {
    if (marker) { map.removeLayer(marker); marker = null; }
    document.getElementById('lat').value = '';
    document.getElementById('lng').value = '';
    document.getElementById('map-hint').textContent = 'برای تعیین موقعیت، روی نقشه کلیک کنید.';
}

// Update lat/lng inputs directly → move map
['lat','lng'].forEach(function(id){
    document.getElementById(id).addEventListener('change', function(){
        var lat = parseFloat(document.getElementById('lat').value);
        var lng = parseFloat(document.getElementById('lng').value);
        if (!isNaN(lat) && !isNaN(lng)) {
            var ll = L.latLng(lat, lng);
            map.setView(ll, 13);
            if (marker) marker.setLatLng(ll);
            else {
                marker = L.marker(ll, {draggable: true}).addTo(map);
                marker.on('dragend', updateFromMarker);
            }
            updateHint(lat, lng);
        }
    });
});

// ── Image preview ─────────────────────────────────────────────────────────────
document.getElementById('image-input').addEventListener('change', function(){
    var preview = document.getElementById('image-preview');
    preview.innerHTML = '';
    Array.from(this.files).slice(0,8).forEach(function(file){
        var reader = new FileReader();
        reader.onload = function(e){
            var div = document.createElement('div');
            div.style.cssText = 'position:relative;width:90px;height:90px;';
            div.innerHTML = '<img src="'+e.target.result+'" style="width:90px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6;">';
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
});
</script>
@endpush
