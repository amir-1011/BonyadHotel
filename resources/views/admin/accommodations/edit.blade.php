<div>

<div class="card shadow-sm">
    <div class="card-header py-2 d-flex align-items-center justify-content-end gap-2 flex-wrap">
        <a wire:navigate href="{{ route('admin.room-types.index', $accommodation) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-door-open me-1"></i>اتاق‌ها</a>
        <a wire:navigate href="{{ route('admin.accommodations.veteran-policy', $accommodation) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-shield-check me-1"></i>تعاریف اولیه</a>
        <a wire:navigate href="{{ route('admin.accommodations.cancellation-policy', $accommodation) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-x-circle me-1"></i>سیاست کنسلی</a>
        <a wire:navigate href="{{ route('admin.accommodations.medical-accommodation', $accommodation) }}" class="btn btn-sm btn-outline-info"><i class="bi bi-heart-pulse me-1"></i>اسکان درمانی</a>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger py-1 small">{{ $errors->first() }}</div>
        @endif
        <div class="row g-3">
            @include('admin.accommodations._form', ['editing'=>true])

            <div class="col-12">
                <label class="form-label small fw-semibold"><i class="bi bi-geo-alt me-1"></i>موقعیت روی نقشه <span class="text-muted fw-normal">(اختیاری — روی نقشه کلیک کنید)</span></label>
                <div id="map-picker" wire:ignore style="height:320px;border-radius:10px;border:1px solid #dee2e6;"></div>
                <div class="d-flex gap-3 mt-2 align-items-center" wire:ignore>
                    <div class="text-muted small flex-grow-1" id="map-hint">
                        @if($accommodation->lat)
                            <i class="bi bi-check-circle-fill text-success me-1"></i>موقعیت ذخیره شده: {{ $accommodation->lat }}، {{ $accommodation->lng }}
                        @else
                            برای تعیین موقعیت، روی نقشه کلیک کنید.
                        @endif
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearMapMarker()">
                        <i class="bi bi-x-circle me-1"></i>پاک‌کردن
                    </button>
                </div>
            </div>

            <x-accommodation.image-gallery-editor
                :images="$accommodation->images ?? []"
                :keep-images="$keepImages"
                :featured-image="$image"
                :show-removal-state="true"
            />

            <div class="col-12">
                <x-image-upload.livewire-panel model="newImages">
                    <div class="mt-3">
                        <x-image-upload.submit-button action="update" label="ذخیره تغییرات" />
                    </div>
                </x-image-upload.livewire-panel>
            </div>
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
        if (marker) { marker.setLatLng(e.latlng); }
        else {
            marker = L.marker(e.latlng, {draggable: true}).addTo(map);
            marker.on('dragend', updateFromMarker);
        }
        updateHint(lat, lng);
    });

    function updateFromMarker(e) {
        var pos = e.target.getLatLng();
        @this.set('lat', pos.lat.toFixed(6), false);
        @this.set('lng', pos.lng.toFixed(6), false);
        updateHint(pos.lat.toFixed(6), pos.lng.toFixed(6));
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
        var hint = document.getElementById('map-hint');
        if (hint) hint.textContent = 'برای تعیین موقعیت، روی نقشه کلیک کنید.';
    };
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
