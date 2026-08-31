<div>

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
                <label class="form-label small fw-semibold">قیمت/شب/تخت (ریال)</label>
                <x-money-input wire:model="pricePerNight" class="form-control @error('pricePerNight') is-invalid @enderror" min="0" />
                @error('pricePerNight')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">ظرفیت (نفر)</label>
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
                <textarea wire:model="amenitiesRaw" class="form-control" rows="4" placeholder="Wi-Fi, پارکینگ, استخر"></textarea>
            </div>

                {{-- Map Picker --}}
                <div class="col-12">
                    <label class="form-label small fw-semibold"><i class="bi bi-geo-alt me-1"></i>موقعیت روی نقشه <span class="text-muted fw-normal">(اختیاری — روی نقشه کلیک کنید)</span></label>
                    <div id="map-picker" wire:ignore style="height:320px;border-radius:10px;border:1px solid #dee2e6;"></div>
                    <div class="d-flex gap-3 mt-2">
                        <div class="flex-grow-1">
                            <input wire:model="lat" type="number" step="any" id="lat" class="form-control form-control-sm" placeholder="عرض جغرافیایی">
                        </div>
                        <div class="flex-grow-1">
                            <input wire:model="lng" type="number" step="any" id="lng" class="form-control form-control-sm" placeholder="طول جغرافیایی">
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearMarker()">
                            <i class="bi bi-x-circle me-1"></i>پاک‌کردن
                        </button>
                    </div>
                    <div id="map-hint" class="text-muted small mt-1">برای تعیین موقعیت، روی نقشه کلیک کنید.</div>
                </div>

                {{-- Image Upload --}}
                <div class="col-12">
                    <x-image-upload.livewire-panel model="images" label="تصاویر اقامتگاه">
                        <div class="mt-3">
                            <x-image-upload.submit-button action="store" :upload-targets="'images'" label="ثبت اقامتگاه" class="btn btn-success px-4" />
                        </div>
                    </x-image-upload.livewire-panel>
                </div>

                <div class="col-12">
                    <div class="alert alert-info small py-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>اقامتگاه شما پس از بررسی و تأیید مدیر نمایش داده خواهد شد.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>


@push('styles')
<link rel="stylesheet" href="https://static.neshan.org/sdk/leaflet/v1.9.4/neshan-sdk/v1.0.8/index.css">
@endpush

@push('scripts')
<script src="https://static.neshan.org/sdk/leaflet/v1.9.4/neshan-sdk/v1.0.8/index.js" id="neshan-sdk-accommodation"></script>
<script>
// ── Province → City AJAX ──────────────────────────────────────────────────────
document.getElementById('province_id')?.addEventListener('change', function(){
    fetch(`/api/provinces/${this.value}/cities`).then(r=>r.json()).then(cities=>{
        var sel = document.getElementById('city_id');
        sel.innerHTML = cities.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
    });
});

// ── Leaflet Map Picker ────────────────────────────────────────────────────────
var _accommodationMap = null;

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

    var latInput = document.getElementById('lat');
    var lngInput = document.getElementById('lng');
    var initLat = parseFloat(latInput?.value) || 32.4279;
    var initLng = parseFloat(lngInput?.value) || 53.6880;
    var hasInit = latInput?.value !== '';

    var map = new L.Map('map-picker', {
        key: 'web.75d28da947f74d85972934574838fa0e',
        maptype: 'dreamy',
        center: [initLat, initLng],
        zoom: hasInit ? 13 : 5,
    });
    mapEl._leafletMap = map;
    _accommodationMap = map;

    var marker = null;
    if (hasInit) {
        marker = L.marker([initLat, initLng], {draggable: true}).addTo(map);
        marker.on('dragend', updateFromMarker);
        updateHint(initLat, initLng);
    }

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
    window.clearMarker = function () {
        if (marker) { map.removeLayer(marker); marker = null; }
        if (latInput) latInput.value = '';
        if (lngInput) lngInput.value = '';
        document.getElementById('map-hint').textContent = 'برای تعیین موقعیت، روی نقشه کلیک کنید.';
    };

    ['lat','lng'].forEach(function(id){
        document.getElementById(id)?.addEventListener('change', function(){
            var lat = parseFloat(latInput?.value);
            var lng = parseFloat(lngInput?.value);
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
};

function tryInitAccommodationMap() {
    if (!window.L) return;
    requestAnimationFrame(function () { requestAnimationFrame(leafletReady); });
}
tryInitAccommodationMap();
document.getElementById('neshan-sdk-accommodation')?.addEventListener('load', tryInitAccommodationMap);
document.addEventListener('livewire:navigating', destroyAccommodationMap);
document.addEventListener('livewire:navigated', tryInitAccommodationMap);
</script>
@endpush
