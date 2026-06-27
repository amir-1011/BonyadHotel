<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('host.accommodations.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">ویرایش: {{ $accommodation->name }}</h5>
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
                <label class="form-label small fw-semibold">قیمت/شب (تومان)</label>
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
                <div id="map-picker" style="height:320px;border-radius:10px;border:1px solid #dee2e6;"></div>
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
                <div id="map-hint" class="text-muted small mt-1">
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
                        <button type="button" wire:click="removeExistingImage('{{ $img }}')" data-swal-confirm="این تصویر حذف شود؟" class="btn btn-xs btn-danger" style="position:absolute;top:2px;left:2px;padding:.1rem .35rem;font-size:.75rem;" title="حذف">×</button>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- New Image Upload --}}
            <div class="col-12">
                <label class="form-label small fw-semibold"><i class="bi bi-plus-circle me-1"></i>افزودن تصاویر جدید <span class="text-muted fw-normal">(حداکثر ۸ عکس، هر کدام تا ۴ مگابایت)</span></label>
                <input wire:model="newImages" type="file" class="form-control" accept="image/*" multiple>
                @error('newImages.*')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
                <button wire:click="update" wire:loading.attr="disabled" class="btn btn-success px-4">
                    <span wire:loading wire:target="update" class="spinner-border spinner-border-sm me-1"></span>
                    ذخیره تغییرات
                </button>
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
function clearMarker() {
    if (marker) { map.removeLayer(marker); marker = null; }
    @this.set('lat', '');
    @this.set('lng', '');
    document.getElementById('map-hint').textContent = 'برای تعیین موقعیت، روی نقشه کلیک کنید.';
}
</script>
@endpush