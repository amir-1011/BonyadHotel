@extends('layouts.admin')
@section('title', 'ویرایش اقامتگاه')
@section('page-title', 'ویرایش اقامتگاه')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.accommodations.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <a href="{{ route('admin.room-types.index', $accommodation) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-door-open me-1"></i>اتاق‌ها</a>
    <h5 class="fw-bold mb-0">ویرایش: {{ $accommodation->name }}</h5>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.accommodations.update', $accommodation) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="row g-3">
                @include('admin.accommodations._form', ['editing'=>true])

                <div class="col-12">
                    <label class="form-label small fw-semibold"><i class="bi bi-geo-alt me-1"></i>موقعیت روی نقشه <span class="text-muted fw-normal">(اختیاری — روی نقشه کلیک کنید)</span></label>
                    <div id="map-picker" style="height:320px;border-radius:10px;border:1px solid #dee2e6;"></div>
                    <div class="d-flex gap-3 mt-2">
                        <div class="flex-grow-1">
                            <input type="number" step="any" name="lat" id="lat" class="form-control form-control-sm" placeholder="عرض جغرافیایی" value="{{ old('lat', $accommodation->lat) }}">
                        </div>
                        <div class="flex-grow-1">
                            <input type="number" step="any" name="lng" id="lng" class="form-control form-control-sm" placeholder="طول جغرافیایی" value="{{ old('lng', $accommodation->lng) }}">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearMarker()">
                            <i class="bi bi-x-circle me-1"></i>پاک‌کردن
                        </button>
                    </div>
                    <div id="map-hint" class="text-muted small mt-1">
                        @if($accommodation->lat)
                            <i class="bi bi-check-circle-fill text-success me-1"></i>موقعیت ذخیره شده: {{ $accommodation->lat }}، {{ $accommodation->lng }}
                        @else
                            برای تعیین موقعیت، روی نقشه کلیک کنید.
                        @endif
                    </div>
                </div>

                @if(!empty($accommodation->images))
                <div class="col-12">
                    <label class="form-label small fw-semibold"><i class="bi bi-images me-1"></i>تصاویر فعلی <span class="text-muted fw-normal">(تیک را بردارید تا حذف شود)</span></label>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach($accommodation->images as $img)
                        <div class="text-center" style="position:relative;width:110px;">
                            <img src="{{ asset('storage/' . $img) }}" style="width:110px;height:90px;object-fit:cover;border-radius:8px;border:2px solid #dee2e6;" alt="تصویر">
                            <div class="form-check mt-1 justify-content-center d-flex">
                                <input class="form-check-input" type="checkbox" name="keep_images[]" value="{{ $img }}" id="keep-{{ $loop->index }}" checked>
                                <label class="form-check-label small ms-1 text-success" for="keep-{{ $loop->index }}">نگه‌داشتن</label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="col-12">
                    <label class="form-label small fw-semibold"><i class="bi bi-plus-circle me-1"></i>افزودن تصاویر جدید <span class="text-muted fw-normal">(حداکثر ۸ عکس، هر کدام تا ۴ مگابایت)</span></label>
                    <input type="file" name="new_images[]" id="image-input" class="form-control" accept="image/*" multiple>
                    <div id="image-preview" class="d-flex flex-wrap gap-2 mt-2"></div>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary px-4">ذخیره تغییرات</button>
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
document.getElementById('province_id')?.addEventListener('change', function(){
    fetch(`/api/provinces/${this.value}/cities`).then(r=>r.json()).then(cities=>{
        var sel = document.getElementById('city_id');
        sel.innerHTML = cities.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
    });
});

var savedLat = parseFloat('{{ $accommodation->lat ?? "" }}') || null;
var savedLng = parseFloat('{{ $accommodation->lng ?? "" }}') || null;
var centerLat = savedLat || 32.4279;
var centerLng = savedLng || 53.6880;
var zoom      = savedLat ? 13 : 5;

var leafletReady = function () {
    var map = L.map('map-picker').setView([centerLat, centerLng], zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    var marker = null;
    if (savedLat) {
        marker = L.marker([savedLat, savedLng], {draggable: true}).addTo(map);
        marker.on('dragend', updateFromMarker);
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
    window.clearMarker = function () {
        if (marker) { map.removeLayer(marker); marker = null; }
        document.getElementById('lat').value = '';
        document.getElementById('lng').value = '';
        document.getElementById('map-hint').textContent = 'برای تعیین موقعیت، روی نقشه کلیک کنید.';
    }
};

if (window.L) {
    leafletReady();
}

document.getElementById('image-input')?.addEventListener('change', function(){
    var preview = document.getElementById('image-preview');
    preview.innerHTML = '';
    Array.from(this.files).slice(0,8).forEach(function(file){
        var reader = new FileReader();
        reader.onload = function(e){
            var div = document.createElement('div');
            div.style.cssText = 'width:90px;height:90px;';
            div.innerHTML = '<img src="'+e.target.result+'" style="width:90px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #dee2e6;">';
            preview.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
});
</script>
@endpush
