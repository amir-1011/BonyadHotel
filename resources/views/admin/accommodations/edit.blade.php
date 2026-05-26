<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('admin.accommodations.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <a wire:navigate href="{{ route('admin.room-types.index', $accommodation) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-door-open me-1"></i>اتاق‌ها</a>
    <h5 class="fw-bold mb-0">ویرایش: {{ $accommodation->name }}</h5>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger py-1 small">{{ $errors->first() }}</div>
        @endif
        <div class="row g-3">
            @include('admin.accommodations._form', ['editing'=>true])

            <div class="col-12">
                <label class="form-label small fw-semibold"><i class="bi bi-geo-alt me-1"></i>موقعیت روی نقشه <span class="text-muted fw-normal">(اختیاری — روی نقشه کلیک کنید)</span></label>
                <div id="map-picker" style="height:320px;border-radius:10px;border:1px solid #dee2e6;"></div>
                <div class="d-flex gap-3 mt-2 align-items-center">
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

            @if(!empty($accommodation->images))
            <div class="col-12">
                <label class="form-label small fw-semibold"><i class="bi bi-images me-1"></i>تصاویر فعلی <span class="text-muted fw-normal">(کلیک روی × برای حذف)</span></label>
                <div class="d-flex flex-wrap gap-3">
                    @foreach($accommodation->images as $img)
                    <div class="text-center" style="position:relative;width:110px;">
                        <img src="{{ asset('storage/' . $img) }}" style="width:110px;height:90px;object-fit:cover;border-radius:8px;border:2px solid {{ in_array($img, $keepImages) ? '#dee2e6' : '#dc3545' }};" alt="تصویر">
                        @if(in_array($img, $keepImages))
                            <button wire:click="removeExistingImage('{{ $img }}')" type="button" class="btn btn-xs btn-danger" style="position:absolute;top:2px;right:2px;padding:1px 5px;font-size:.7rem;">×</button>
                        @else
                            <div class="text-danger small mt-1">حذف خواهد شد</div>
                        @endif
                    </div>
                    @endforeach
                    </div>
                </div>
            @endif

            <div class="col-12">
                <label class="form-label small fw-semibold"><i class="bi bi-plus-circle me-1"></i>افزودن تصاویر جدید <span class="text-muted fw-normal">(حداکثر ۸ عکس، هر کدام تا ۴ مگابایت)</span></label>
                <input type="file" wire:model="newImages" id="image-input" class="form-control" accept="image/*" multiple>
                <div id="image-preview" class="d-flex flex-wrap gap-2 mt-2"></div>
            </div>

            <div class="col-12">
                <button wire:click="update" class="btn btn-primary px-4">ذخیره تغییرات</button>
            </div>
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
var savedLat = parseFloat('{{ $accommodation->lat ?? "" }}') || null;
var savedLng = parseFloat('{{ $accommodation->lng ?? "" }}') || null;
var centerLat = savedLat || 32.4279;
var centerLng = savedLng || 53.6880;
var zoom      = savedLat ? 13 : 5;

var leafletReady = function () {
    var map = new L.Map('map-picker', {
        key: 'web.75d28da947f74d85972934574838fa0e',
        maptype: 'dreamy',
        center: [centerLat, centerLng],
        zoom: zoom,
    });

    var marker = null;
    if (savedLat) {
        marker = L.marker([savedLat, savedLng], {draggable: true}).addTo(map);
        marker.on('dragend', updateFromMarker);
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
    window.clearMapMarker = function () {
        if (marker) { map.removeLayer(marker); marker = null; }
        @this.set('lat', '');
        @this.set('lng', '');
        document.getElementById('map-hint').textContent = 'برای تعیین موقعیت، روی نقشه کلیک کنید.';
    }
};

if (window.L) { leafletReady(); }
</script>
@endpush
