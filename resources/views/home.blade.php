@extends('layouts.app')

@section('title', 'رزرو اقامتگاه')

@section('content')

{{-- Hero --}}
<div class="text-center py-4 py-md-5 bg-primary text-white rounded-3 mb-4" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%) !important;">
    <h1 class="fw-bold mb-2 fs-3 fs-md-1"><i class="bi bi-house-heart-fill me-2"></i>رزرو اقامتگاه</h1>
    <p class="mb-0 small">بهترین اقامتگاه‌ها را در سراسر ایران پیدا و رزرو کنید</p>
    @auth
        @if(Auth::user()->discount_percentage > 0)
            <div class="mt-3">
                <span class="badge bg-warning text-dark px-3 py-2 fs-6">
                    <i class="bi bi-star-fill me-1"></i>
                    شما {{ Auth::user()->discount_percentage }}٪ تخفیف ویژه دارید
                </span>
            </div>
        @endif
    @endauth
</div>

{{-- Search Form --}}
<div class="card p-4 mb-4 shadow">
    <h5 class="fw-bold mb-3"><i class="bi bi-search me-2 text-primary"></i>جستجوی اقامتگاه</h5>
    <form action="{{ route('accommodations.index') }}" method="GET" id="searchForm">

        <div class="row g-3">
            {{-- Location Mode Tabs --}}
            <div class="col-12">
                <ul class="nav nav-tabs" id="locationTabs">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-city" data-bs-toggle="tab" href="#pane-city">
                            <i class="bi bi-geo-alt me-1"></i>انتخاب استان/شهر
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-map" data-bs-toggle="tab" href="#pane-map">
                            <i class="bi bi-map me-1"></i>انتخاب روی نقشه
                        </a>
                    </li>
                </ul>
                <div class="tab-content border border-top-0 rounded-bottom p-3 bg-light">
                    {{-- City/Province Pane --}}
                    <div class="tab-pane fade show active" id="pane-city">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">استان</label>
                                <select name="province_id" id="provinceSelect" class="form-select select2-basic">
                                    <option value="">انتخاب استان</option>
                                    @foreach($provinces as $province)
                                        <option value="{{ $province->id }}">{{ $province->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">شهر</label>
                                <select name="city_id" id="citySelect" class="form-select select2-basic" disabled>
                                    <option value="">ابتدا استان را انتخاب کنید</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    {{-- Map Pane --}}
                    <div class="tab-pane fade" id="pane-map">
                        <p class="small text-muted mb-2"><i class="bi bi-cursor me-1"></i>روی نقشه کلیک کنید تا شهر نزدیک انتخاب شود</p>
                        <div id="map"></div>
                        <input type="hidden" name="map_lat" id="mapLat">
                        <input type="hidden" name="map_lng" id="mapLng">
                        <div id="mapSelectedCity" class="mt-2 text-success small d-none">
                            <i class="bi bi-check-circle me-1"></i> <span id="mapCityLabel"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Date Range --}}
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label fw-semibold"><i class="bi bi-calendar-range me-1"></i>بازه تاریخ اقامت</label>
                <div class="range-picker-trigger border rounded-3 px-3 py-2 bg-white d-flex align-items-center justify-content-between"
                     data-bs-toggle="collapse" data-bs-target="#homeDateCal">
                    <div id="homeDateDisplay"><span class="text-muted">تاریخ ورود را انتخاب کنید</span></div>
                    <i class="bi bi-calendar3 text-primary"></i>
                </div>
                <div class="range-picker-phase text-info">
                    <i class="bi bi-info-circle me-1"></i>کلیک اول: ورود — کلیک دوم: خروج
                </div>
                <div class="collapse mt-1" id="homeDateCal">
                    <div class="range-picker-cal"><div id="homeCalEl"></div></div>
                </div>
                <input type="hidden" name="check_in" id="checkIn">
                <input type="hidden" name="check_out" id="checkOut">
            </div>

            {{-- Guests --}}
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label fw-semibold"><i class="bi bi-people me-1"></i>تعداد مهمان</label>
                <select name="guests" class="form-select">
                    @for($i = 1; $i <= 10; $i++)
                        <option value="{{ $i }}">{{ $i }} نفر</option>
                    @endfor
                </select>
            </div>

            {{-- Wheelchair --}}
            <div class="col-12 col-sm-6 col-md-4 d-flex align-items-end">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" name="wheelchair" id="wheelchairFilter" value="1">
                    <label class="form-check-label fw-semibold" for="wheelchairFilter">
                        <i class="bi bi-wheelchair text-primary me-1"></i>مناسب ویلچر
                    </label>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label d-none d-sm-block invisible">.</label>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-2"></i>جستجو
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Discount Info --}}
<div class="card p-4 bg-light border-0">
    <h6 class="fw-bold mb-3"><i class="bi bi-percent text-success me-2"></i>جدول تخفیف‌های ویژه</h6>
    <div class="row g-2">
        @foreach([
            ['خانواده شهید', '50', 'danger'],
            ['جانباز ۷۰٪ و بالاتر', '50', 'danger'],
            ['خانواده آزاده', '40', 'warning'],
            ['جانباز ۵۰ تا ۶۹ درصد', '40', 'warning'],
            ['جانباز ۲۵ تا ۴۹ درصد', '30', 'info'],
        ] as [$label, $pct, $color])
            <div class="col-12 col-sm-6 col-md-4">
                <div class="badge bg-{{ $color }} text-white w-100 py-2 rounded-3" style="font-size:.85rem;white-space:normal;">
                    {{ $label }}: {{ $pct }}٪ تخفیف
                </div>
            </div>
        @endforeach
    </div>
    @guest
        <p class="text-muted small mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>
            برای استفاده از تخفیف‌ها، <a href="{{ route('auth.mobile') }}">وارد شوید</a> و کد ملی خود را تأیید کنید.
        </p>
    @endguest
</div>

@endsection

@push('scripts')
<script>
// Province → City AJAX
$('#provinceSelect').on('change', function() {
    const pid = $(this).val();
    const citySelect = $('#citySelect');
    citySelect.prop('disabled', true).html('<option value="">در حال بارگذاری...</option>');
    if (!pid) { citySelect.html('<option value="">ابتدا استان را انتخاب کنید</option>'); return; }
    $.getJSON(`/api/provinces/${pid}/cities`, function(data) {
        let opts = '<option value="">انتخاب شهر (اختیاری)</option>';
        data.forEach(c => opts += `<option value="${c.id}">${c.name}</option>`);
        citySelect.html(opts).prop('disabled', false);
        citySelect.trigger('change.select2');
    });
});

// Select2 init
$('.select2-basic').select2({ theme: 'bootstrap-5', language: 'fa', width: '100%' });

// Persian Jalali range picker
initJalaliRange('#homeCalEl', '#checkIn', '#checkOut', '#homeDateDisplay');

// Leaflet Map
const map = L.map('map').setView([32.4279, 53.6880], 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
}).addTo(map);

let mapMarker = null;
const cities = {!! json_encode($citiesForMap) !!};

map.on('click', function(e) {
    const { lat, lng } = e.latlng;
    $('#mapLat').val(lat.toFixed(6));
    $('#mapLng').val(lng.toFixed(6));
    if (mapMarker) map.removeLayer(mapMarker);
    mapMarker = L.marker([lat, lng]).addTo(map);

    // Find nearest city
    let nearest = null, minDist = Infinity;
    cities.forEach(c => {
        if (!c.lat || !c.lng) return;
        const d = Math.hypot(c.lat - lat, c.lng - lng);
        if (d < minDist) { minDist = d; nearest = c; }
    });
    if (nearest && minDist < 2) {
        $('#mapCityLabel').text(`${nearest.province} - ${nearest.name}`);
        $('#mapSelectedCity').removeClass('d-none');
        // inject city_id into form hidden field
        $('input[name=city_id_map]').remove();
        $('<input>').attr({type:'hidden', name:'city_id', value: nearest.id}).appendTo('#searchForm');
    }
});
</script>
@endpush
