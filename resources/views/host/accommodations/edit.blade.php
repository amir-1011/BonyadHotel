<div>

@php
    $canEditAccommodation = auth()->user()?->hostCan('accommodations.edit', 'edit') ?? true;
@endphp

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('host.accommodations.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label small fw-semibold">نام اقامتگاه</label>
                <input wire:model="name" type="text" class="form-control @error('name') is-invalid @enderror">
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @include('components.accommodation.type-field', ['accommodationTypes' => $accommodationTypes])
            @include('components.accommodation.location-fields', ['provinces' => $provinces, 'cities' => $cities, 'counties' => $counties])
            <div class="col-md-4 d-none">
                <label class="form-label small fw-semibold">قیمت/شب/تخت (تومان)</label>
                <x-money-input wire:model="pricePerNight" class="form-control @error('pricePerNight') is-invalid @enderror" min="0" />
                @error('pricePerNight')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">ظرفیت</label>
                <input wire:model="capacity" type="number" class="form-control @error('capacity') is-invalid @enderror" min="1">
                @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">اتاق‌ها</label>
                <input wire:model="rooms" type="number" class="form-control @error('rooms') is-invalid @enderror" min="1">
                @error('rooms')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @include('components.accommodation.children-under-6-policy')
            <div class="col-12">
                <label class="form-label small fw-semibold">آدرس</label>
                <input wire:model="address" type="text" class="form-control">
            </div>

            @include('components.accommodation.management-and-phones')

            <div class="col-12">
                <label class="form-label small fw-semibold">توضیحات</label>
                <textarea wire:model="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">امکانات (با کاما جدا کنید)</label>
                <textarea wire:model="amenitiesRaw" class="form-control" rows="4"></textarea>
            </div>

            {{-- Map Picker --}}
            <div class="col-12">
                <label class="form-label small fw-semibold"><i class="bi bi-geo-alt me-1"></i>موقعیت روی نقشه <span class="text-muted fw-normal">(اختیاری — روی نقشه کلیک کنید)</span></label>
                <div id="map-picker" wire:ignore style="height:320px;border-radius:10px;border:1px solid #dee2e6;"></div>
                <div class="d-flex gap-3 mt-2" wire:ignore>
                    <div class="flex-grow-1">
                        <input type="number" step="any" id="lat" class="form-control form-control-sm" placeholder="عرض جغرافیایی" value="{{ $lat }}">
                    </div>
                    <div class="flex-grow-1">
                        <input type="number" step="any" id="lng" class="form-control form-control-sm" placeholder="طول جغرافیایی" value="{{ $lng }}">
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearMapMarker()">
                        <i class="bi bi-x-circle me-1"></i>پاک‌کردن
                    </button>
                </div>
                <div id="map-hint" class="text-muted small mt-1" wire:ignore>
                    @if($accommodation->lat)
                        <i class="bi bi-check-circle-fill text-success me-1"></i>موقعیت ذخیره شده: {{ $accommodation->lat }}، {{ $accommodation->lng }}
                    @else
                        برای تعیین موقعیت، روی نقشه کلیک کنید.
                    @endif
                </div>
            </div>

            {{-- Existing Images --}}
            @if(!empty($keepImages))
            <div class="col-12">
                <label class="form-label small fw-semibold"><i class="bi bi-images me-1"></i>تصاویر فعلی <span class="text-muted fw-normal">(× برای حذف)</span></label>
                <div class="d-flex flex-wrap gap-3">
                    @foreach($keepImages as $img)
                    <div class="text-center" style="position:relative;width:110px;">
                        <img src="{{ asset('storage/' . $img) }}" style="width:110px;height:90px;object-fit:cover;border-radius:8px;border:2px solid #dee2e6;" alt="تصویر">
                        @if($canEditAccommodation)
                        <button type="button" wire:click="removeExistingImage('{{ $img }}')" data-swal-confirm="این تصویر حذف شود؟" class="btn btn-xs btn-danger" style="position:absolute;top:2px;left:2px;padding:.1rem .35rem;font-size:.75rem;" title="حذف">×</button>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- New Image Upload --}}
            @if($canEditAccommodation)
            <div class="col-12">
                <x-image-upload.livewire-panel model="newImages">
                    <div class="mt-3">
                        <x-image-upload.submit-button action="update" label="ذخیره تغییرات" class="btn btn-success px-4" />
                    </div>
                </x-image-upload.livewire-panel>
            </div>
            @else
            <div class="col-12">
                <div class="alert alert-warning small mb-0"><i class="bi bi-lock me-1"></i>فقط مجوز مشاهده دارید — امکان ویرایش و ذخیره وجود ندارد.</div>
            </div>
            @endif
        </div>
    </div>
</div>

</div>

@push('styles')
<link rel="stylesheet" href="https://static.neshan.org/sdk/leaflet/v1.9.4/neshan-sdk/v1.0.8/index.css">
@endpush

@push('scripts')
<script>
var NESHAN_SDK = 'https://static.neshan.org/sdk/leaflet/v1.9.4/neshan-sdk/v1.0.8/index.js';
var _accommodationMap = null;
var _neshanLoading = false;
var savedLat = parseFloat(@js($accommodation->lat)) || null;
var savedLng = parseFloat(@js($accommodation->lng)) || null;

function restoreVendorLeafletIfNeeded() {
    if (!document.getElementById('map-picker') && window.__leafletVendorBackup) {
        window.L = window.__leafletVendorBackup;
        delete window.__leafletVendorBackup;
    }
}

function destroyAccommodationMap() {
    if (_accommodationMap) {
        try { _accommodationMap.remove(); } catch (e) {}
        _accommodationMap = null;
    }
    var el = document.getElementById('map-picker');
    if (el) el._leafletMap = null;
}

function syncLatLngInputs(lat, lng) {
    var latEl = document.getElementById('lat');
    var lngEl = document.getElementById('lng');
    if (latEl) latEl.value = lat ?? '';
    if (lngEl) lngEl.value = lng ?? '';
}

var leafletReady = function () {
    var mapEl = document.getElementById('map-picker');
    if (!mapEl || !window.L || !mapEl.isConnected || !mapEl.offsetWidth) return;
    if (mapEl._leafletMap) { mapEl._leafletMap.invalidateSize(); return; }

    destroyAccommodationMap();

    var centerLat = savedLat || 32.4279;
    var centerLng = savedLng || 53.6880;
    var zoom = savedLat ? 13 : 5;

    var map = new L.Map('map-picker', {
        key: 'web.75d28da947f74d85972934574838fa0e',
        maptype: 'dreamy',
        center: [centerLat, centerLng],
        zoom: zoom,
    });
    mapEl._leafletMap = map;
    _accommodationMap = map;

    var marker = null;
    if (savedLat && savedLng) {
        marker = L.marker([savedLat, savedLng], {draggable: true}).addTo(map);
        marker.on('dragend', updateFromMarker);
    }

    map.on('click', function(e) {
        var lat = e.latlng.lat.toFixed(6);
        var lng = e.latlng.lng.toFixed(6);
        @this.set('lat', lat, false);
        @this.set('lng', lng, false);
        syncLatLngInputs(lat, lng);
        if (marker) { marker.setLatLng(e.latlng); }
        else {
            marker = L.marker(e.latlng, {draggable: true}).addTo(map);
            marker.on('dragend', updateFromMarker);
        }
        updateHint(lat, lng);
    });

    function updateFromMarker(e) {
        var pos = e.target.getLatLng();
        var lat = pos.lat.toFixed(6);
        var lng = pos.lng.toFixed(6);
        @this.set('lat', lat, false);
        @this.set('lng', lng, false);
        syncLatLngInputs(lat, lng);
        updateHint(lat, lng);
    }
    function updateHint(lat, lng) {
        var hint = document.getElementById('map-hint');
        if (!hint) return;
        hint.innerHTML =
            '<i class="bi bi-check-circle-fill text-success me-1"></i>موقعیت انتخاب شد: ' + lat + '، ' + lng;
    }
    window.clearMapMarker = function () {
        if (marker) { map.removeLayer(marker); marker = null; }
        @this.set('lat', '', false);
        @this.set('lng', '', false);
        syncLatLngInputs('', '');
        var hint = document.getElementById('map-hint');
        if (hint) hint.textContent = 'برای تعیین موقعیت، روی نقشه کلیک کنید.';
    };

    ['lat', 'lng'].forEach(function (id) {
        var input = document.getElementById(id);
        if (!input || input.dataset.mapBound === '1') return;
        input.dataset.mapBound = '1';
        input.addEventListener('change', function () {
            var lat = parseFloat(document.getElementById('lat')?.value);
            var lng = parseFloat(document.getElementById('lng')?.value);
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
            @this.set('lat', lat.toFixed(6), false);
            @this.set('lng', lng.toFixed(6), false);
            var ll = L.latLng(lat, lng);
            map.setView(ll, Math.max(map.getZoom(), 13));
            if (marker) marker.setLatLng(ll);
            else {
                marker = L.marker(ll, {draggable: true}).addTo(map);
                marker.on('dragend', updateFromMarker);
            }
            updateHint(lat.toFixed(6), lng.toFixed(6));
        });
    });
};

function ensureNeshanSdk(callback) {
    if (!document.getElementById('map-picker')) return;

    var sdk = document.getElementById('neshan-sdk-accommodation');
    if (sdk?.dataset.ready === '1' && window.L) {
        callback();
        return;
    }

    if (window.L) {
        window.__leafletVendorBackup = window.L;
        delete window.L;
    }

    if (_neshanLoading) return;
    _neshanLoading = true;

    if (sdk) sdk.remove();

    var script = document.createElement('script');
    script.id = 'neshan-sdk-accommodation';
    script.src = NESHAN_SDK;
    script.onload = function () {
        script.dataset.ready = '1';
        _neshanLoading = false;
        callback();
    };
    script.onerror = function () { _neshanLoading = false; };
    document.body.appendChild(script);
}

function tryInitAccommodationMap() {
    ensureNeshanSdk(function () {
        requestAnimationFrame(function () { requestAnimationFrame(leafletReady); });
    });
}

document.addEventListener('livewire:navigating', function () {
    destroyAccommodationMap();
    _neshanLoading = false;
    restoreVendorLeafletIfNeeded();
});
document.addEventListener('livewire:navigated', tryInitAccommodationMap);
document.addEventListener('DOMContentLoaded', tryInitAccommodationMap);
tryInitAccommodationMap();
</script>
@endpush
