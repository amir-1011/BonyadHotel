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
            <div id="map-picker" style="height:320px;border-radius:10px;border:1px solid #dee2e6;"></div>
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
<script src="https://static.neshan.org/sdk/leaflet/v1.9.4/neshan-sdk/v1.0.8/index.js"></script>
<script>
var map = new L.Map('map-picker', {
    key: 'web.75d28da947f74d85972934574838fa0e',
    maptype: 'dreamy',
    center: [32.4279, 53.6880],
    zoom: 5,
});

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
</script>
@endpush
