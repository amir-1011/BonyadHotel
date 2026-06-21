<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('admin.accommodations.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">اقامتگاه جدید</h5>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger py-1 small">{{ $errors->first() }}</div>
        @endif
        @include('admin.accommodations._form', ['editing'=>false])

        <div class="col-12 mt-3">
            <label class="form-label small fw-semibold"><i class="bi bi-geo-alt me-1"></i>موقعیت روی نقشه <span class="text-muted fw-normal">(اختیاری — روی نقشه کلیک کنید)</span></label>
            <div id="map-picker" wire:ignore style="height:320px;border-radius:10px;border:1px solid #dee2e6;"></div>
            <div class="d-flex gap-3 mt-2 align-items-center">
                <div class="text-muted small flex-grow-1" id="map-hint">برای تعیین موقعیت، روی نقشه کلیک کنید.</div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearMapMarker()">
                    <i class="bi bi-x-circle me-1"></i>پاک‌کردن
                </button>
            </div>
        </div>

        <div class="mt-3">
            <button wire:click="store" class="btn btn-primary px-4">ثبت اقامتگاه</button>
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

    var map = new L.Map('map-picker', {
        key: 'web.75d28da947f74d85972934574838fa0e',
        maptype: 'dreamy',
        center: [32.4279, 53.6880],
        zoom: 5,
    });
    mapEl._leafletMap = map;
    _accommodationMap = map;

    var marker = null;

    map.on('click', function(e) {
        var lat = e.latlng.lat.toFixed(6);
        var lng = e.latlng.lng.toFixed(6);
        @this.set('lat', lat);
        @this.set('lng', lng);
        if (marker) { marker.setLatLng(e.latlng); }
        else {
            marker = L.marker(e.latlng, {draggable: true}).addTo(map);
            marker.on('dragend', updateFromMarker);
        }
        updateHint(lat, lng);
    });

    function updateFromMarker(e) {
        var pos = e.target.getLatLng();
        @this.set('lat', pos.lat.toFixed(6));
        @this.set('lng', pos.lng.toFixed(6));
        updateHint(pos.lat.toFixed(6), pos.lng.toFixed(6));
    }
    function updateHint(lat, lng) {
        document.getElementById('map-hint').innerHTML =
            '<i class="bi bi-check-circle-fill text-success me-1"></i>موقعیت انتخاب شد: ' + lat + '، ' + lng;
    }
    window.clearMapMarker = function () {
        if (marker) { map.removeLayer(marker); marker = null; }
        @this.set('lat', '');
        @this.set('lng', '');
        document.getElementById('map-hint').textContent = 'برای تعیین موقعیت، روی نقشه کلیک کنید.';
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
</script>
@endpush
