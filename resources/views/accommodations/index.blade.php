@extends('layouts.app')

@section('title', 'جستجوی اقامتگاه')

@push('styles')
<style>
/* ── Results layout ─────────────────────────────────── */
.results-wrap {
    display: flex;
    min-height: calc(100vh - 130px);
}
.results-left {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px;
    min-width: 0;
}
.results-right {
    width: 440px;
    flex-shrink: 0;
    position: sticky;
    top: 60px;
    height: calc(100vh - 60px);
}
@media (max-width: 991px) {
    .results-right { display: none; }
    .results-left { padding: 16px; }
}

/* Filter bar */
.filter-bar {
    background: #fff;
    border-bottom: 1px solid var(--bnb-border);
    padding: 12px 0;
    margin-bottom: 4px;
}
.filter-bar-inner {
    display: flex;
    gap: 8px;
    align-items: center;
    overflow-x: auto;
    scrollbar-width: none;
    padding: 0 24px;
}
.filter-bar-inner::-webkit-scrollbar { display: none; }

/* Price markers on map */
.leaflet-price-marker {
    background: #fff;
    border: 2px solid var(--bnb-dark);
    border-radius: 20px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: 700;
    font-family: var(--bnb-font);
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
    transition: background .15s, color .15s;
    white-space: nowrap;
}
.leaflet-price-marker:hover,
.leaflet-price-marker.active {
    background: var(--bnb-dark);
    color: #fff;
}

/* Map results mobile toggle */
.map-toggle-btn {
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--bnb-dark);
    color: #fff;
    border: none;
    border-radius: 20px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    font-family: var(--bnb-font);
    cursor: pointer;
    z-index: 999;
    display: flex; align-items: center; gap: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,.25);
}
@media (min-width: 992px) { .map-toggle-btn { display: none; } }

/* Full-screen map on mobile */
.mobile-map-overlay {
    position: fixed; inset: 0;
    z-index: 998;
    display: none;
}
.mobile-map-overlay.active { display: block; }
.mobile-map-close {
    position: absolute;
    top: 16px; right: 16px;
    background: #fff;
    border: none;
    border-radius: 50%;
    width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    z-index: 999;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,.2);
    font-size: 18px;
}
#mapMain, #mapMobile { height: 100%; border-radius: 0; }
</style>
@endpush

@section('content')

{{-- ═══════════════════════════════════
     FILTER BAR
═══════════════════════════════════ --}}
<div class="filter-bar">
    <form action="{{ route('accommodations.index') }}" method="GET" id="filterForm">
        <div class="filter-bar-inner">

            {{-- Location pill --}}
            <button type="button" class="bnb-filter-pill {{ request('province_id') || request('city_id') ? 'active' : '' }}"
                    data-bs-toggle="collapse" data-bs-target="#filterLocation">
                <i class="bi bi-geo-alt me-1"></i>
                @if(request('city_id') && $cities->firstWhere('id', request('city_id')))
                    {{ $cities->firstWhere('id', request('city_id'))->name }}
                @elseif(request('province_id') && $provinces->firstWhere('id', request('province_id')))
                    {{ $provinces->firstWhere('id', request('province_id'))->name }}
                @else
                    مکان
                @endif
            </button>

            {{-- Date pill --}}
            <button type="button" class="bnb-filter-pill {{ request('check_in') ? 'active' : '' }}"
                    data-bs-toggle="collapse" data-bs-target="#filterDates">
                <i class="bi bi-calendar me-1"></i>
                @if(request('check_in') && request('check_out'))
                    {{ \Carbon\Carbon::parse(request('check_in'))->format('m/d') }} - {{ \Carbon\Carbon::parse(request('check_out'))->format('m/d') }}
                @else
                    تاریخ
                @endif
            </button>

            {{-- Guests pill --}}
            <button type="button" class="bnb-filter-pill {{ request('guests') > 1 ? 'active' : '' }}"
                    data-bs-toggle="collapse" data-bs-target="#filterGuests">
                <i class="bi bi-people me-1"></i>{{ request('guests', 1) }} مهمان
            </button>

            {{-- Type filter --}}
            @php $types = ['' => 'همه انواع', 'hotel' => 'هتل', 'villa' => 'ویلا', 'apartment' => 'آپارتمان', 'hostel' => 'هاستل', 'traditional' => 'سنتی']; @endphp
            @foreach($types as $val => $label)
                <a href="{{ route('accommodations.index', array_merge(request()->except('type','page'), $val ? ['type' => $val] : [])) }}"
                   class="bnb-filter-pill text-decoration-none {{ request('type', '') === $val ? 'active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach

            {{-- Wheelchair --}}
            <label class="bnb-filter-pill {{ request('wheelchair') ? 'active' : '' }}" style="cursor:pointer;">
                <input type="checkbox" name="wheelchair" value="1" {{ request('wheelchair') ? 'checked' : '' }}
                       style="display:none;" onchange="document.getElementById('filterForm').submit()">
                <i class="bi bi-wheelchair me-1"></i>ویلچر
            </label>

            {{-- Reset --}}
            @if(request()->hasAny(['province_id','city_id','check_in','check_out','guests','type','wheelchair']))
                <a href="{{ route('accommodations.index') }}" class="bnb-filter-pill text-decoration-none" style="color:var(--bnb-red);border-color:var(--bnb-red);">
                    <i class="bi bi-x me-1"></i>پاک کردن
                </a>
            @endif
        </div>

        {{-- Collapsed filters --}}
        <div class="collapse px-4 pt-3 pb-2" id="filterLocation">
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="bnb-label">استان</label>
                    <select name="province_id" id="provinceSelect" class="bnb-select select2-basic">
                        <option value="">همه استان‌ها</option>
                        @foreach($provinces as $p)
                            <option value="{{ $p->id }}" {{ request('province_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="bnb-label">شهر</label>
                    <select name="city_id" id="citySelect" class="bnb-select select2-basic" {{ $cities->isEmpty() ? 'disabled' : '' }}>
                        <option value="">همه شهرها</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->id }}" {{ request('city_id') == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn-bnb w-100">اعمال</button>
                </div>
            </div>
        </div>
        <div class="collapse px-4 pt-3 pb-2" id="filterDates">
            <div class="row g-2 align-items-end">
                <div class="col-md-7">
                    <div class="range-picker-trigger border rounded-3 px-3 py-2 bg-white d-flex align-items-center justify-content-between"
                         data-bs-toggle="collapse" data-bs-target="#indexDateCal">
                        <div id="indexDateDisplay"><span class="text-muted">انتخاب تاریخ ورود و خروج</span></div>
                        <i class="bi bi-calendar3" style="color:var(--bnb-red)"></i>
                    </div>
                    <div class="range-picker-phase small mt-1" style="color:var(--bnb-gray);">
                        <i class="bi bi-info-circle me-1"></i>کلیک اول: ورود — کلیک دوم: خروج
                    </div>
                    <div class="collapse mt-1" id="indexDateCal">
                        <div class="range-picker-cal"><div id="indexCalEl"></div></div>
                    </div>
                    <input type="hidden" name="check_in" id="checkIn" value="{{ request('check_in') }}">
                    <input type="hidden" name="check_out" id="checkOut" value="{{ request('check_out') }}">
                </div>
                <div class="col-md-3">
                    <label class="bnb-label">تعداد مهمان</label>
                    <select name="guests" class="bnb-select">
                        @for($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}" {{ request('guests') == $i ? 'selected' : '' }}>{{ $i }} نفر</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn-bnb w-100">اعمال</button>
                </div>
            </div>
        </div>
        <div class="collapse px-4 pt-2 pb-2" id="filterGuests">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="bnb-label">تعداد مهمان</label>
                    <select name="guests" id="guestsSelect" class="bnb-select" onchange="this.form.submit()">
                        @for($i = 1; $i <= 10; $i++)
                            <option value="{{ $i }}" {{ request('guests') == $i ? 'selected' : '' }}>{{ $i }} نفر</option>
                        @endfor
                    </select>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- ═══════════════════════════════════
     MAIN SPLIT LAYOUT
═══════════════════════════════════ --}}
<div class="results-wrap">

    {{-- LEFT: Cards --}}
    <div class="results-left">
        {{-- Result count --}}
        <div class="bnb-results-count d-flex align-items-center justify-content-between mb-3">
            <span>
                @if($accommodations->total() > 0)
                    بیش از <strong>{{ $accommodations->total() }}</strong> اقامتگاه
                    @if(request('city_id') && ($cityName = $cities->firstWhere('id', request('city_id'))))
                        در <strong>{{ $cityName->name }}</strong>
                    @elseif(request('province_id') && ($provName = $provinces->firstWhere('id', request('province_id'))))
                        در <strong>{{ $provName->name }}</strong>
                    @else
                        در سراسر ایران
                    @endif
                @else
                    اقامتگاهی یافت نشد
                @endif
            </span>
            @auth
                @if(Auth::user()->discount_percentage > 0)
                    <span class="badge-bnb">{{ Auth::user()->discount_percentage }}٪ تخفیف ویژه</span>
                @endif
            @endauth
        </div>

        @forelse($accommodations as $acc)
            @php
                $coverImg = $acc->image ?: collect($acc->images ?? [])->filter()->first();
                $rating   = $acc->averageRating();
                $rCount   = $acc->reviewCount();
            @endphp
            <a href="{{ route('accommodations.show', $acc) }}?check_in={{ request('check_in') }}&check_out={{ request('check_out') }}&guests={{ request('guests', 1) }}"
               class="text-decoration-none d-block mb-4" data-aos="fade-up">
                <div class="bnb-card" style="display:flex;gap:16px;">
                    {{-- Image --}}
                    <div style="width:220px;min-width:220px;border-radius:var(--bnb-radius);overflow:hidden;background:var(--bnb-bg-light);">
                        @if($coverImg)
                            <img src="{{ asset('storage/' . $coverImg) }}" alt="{{ $acc->name }}"
                                 style="width:100%;height:160px;object-fit:cover;display:block;" loading="lazy">
                        @else
                            <div style="height:160px;display:flex;align-items:center;justify-content:center;font-size:2.5rem;">
                                @if($acc->type==='hotel') 🏨
                                @elseif($acc->type==='villa') 🏡
                                @elseif($acc->type==='apartment') 🏢
                                @elseif($acc->type==='hostel') 🛏
                                @else 🏠
                                @endif
                            </div>
                        @endif
                    </div>
                    {{-- Info --}}
                    <div style="flex:1;padding:8px 4px;min-width:0;">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div style="font-size:11px;color:var(--bnb-gray);margin-bottom:2px;">
                                    {{ $acc->typeLabel() }} · {{ $acc->city->province->name ?? '' }}، {{ $acc->city->name ?? '' }}
                                </div>
                                <div style="font-size:16px;font-weight:600;color:var(--bnb-dark);margin-bottom:4px;">{{ $acc->name }}</div>
                                <div style="font-size:13px;color:var(--bnb-gray);">ظرفیت {{ $acc->capacity }} نفر · {{ $acc->rooms }} اتاق</div>
                                @if($acc->amenities)
                                    <div class="mt-2 d-none d-md-block">
                                        @foreach(array_slice($acc->amenities, 0, 4) as $amenity)
                                            <span style="display:inline-block;background:var(--bnb-bg-light);border:1px solid var(--bnb-border);border-radius:20px;padding:2px 10px;font-size:11px;margin-left:4px;margin-bottom:4px;">{{ $amenity }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            {{-- Heart --}}
                            <button class="bnb-card-heart" style="position:static;padding:0;" onclick="event.preventDefault(); toggleWishlist(this, {{ $acc->id }})">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" style="width:20px;height:20px;fill:rgba(0,0,0,.3);stroke:#fff;stroke-width:2;overflow:visible;">
                                    <path d="M16 28c7-4.73 14-10 14-17a6.98 6.98 0 0 0-7-7c-1.8 0-3.58.68-4.95 2.05L16 8.1l-2.05-2.05a6.98 6.98 0 0 0-9.9 9.9C5.14 17.31 16 28 16 28z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="bnb-divider" style="margin:12px 0;"></div>
                        <div class="d-flex justify-content-between align-items-end">
                            <div>
                                @if($rating > 0)
                                    <div class="bnb-card-rating">
                                        <i class="bi bi-star-fill star"></i>
                                        <span>{{ $rating }}</span>
                                        <span style="color:var(--bnb-gray);font-weight:400;">({{ $rCount }} نظر)</span>
                                    </div>
                                @endif
                                @if(in_array('مناسب ویلچر', $acc->amenities ?? []))
                                    <div class="mt-1" style="font-size:12px;color:var(--bnb-gray);"><i class="bi bi-wheelchair me-1"></i>مناسب ویلچر</div>
                                @endif
                            </div>
                            <div class="text-end">
                                @auth
                                    @if(Auth::user()->discount_percentage > 0)
                                        @php $disc = $acc->price_per_night * (1 - Auth::user()->discount_percentage / 100); @endphp
                                        <div style="font-size:12px;text-decoration:line-through;color:var(--bnb-gray);">{{ number_format($acc->price_per_night) }}</div>
                                        <div class="bnb-card-price" style="color:var(--bnb-red);">{{ number_format($disc) }} <span>تومان / شب</span></div>
                                    @else
                                        <div class="bnb-card-price">{{ number_format($acc->price_per_night) }} <span>تومان / شب</span></div>
                                    @endif
                                @else
                                    <div class="bnb-card-price">{{ number_format($acc->price_per_night) }} <span>تومان / شب</span></div>
                                @endauth
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="text-center py-5">
                <div style="font-size:64px;margin-bottom:16px;">🔍</div>
                <h5 style="color:var(--bnb-dark);font-weight:600;">اقامتگاهی یافت نشد</h5>
                <p style="color:var(--bnb-gray);">فیلترها را تغییر دهید یا جستجوی جدیدی انجام دهید</p>
                <a href="{{ route('accommodations.index') }}" class="btn-bnb" style="display:inline-block;margin-top:8px;text-decoration:none;">پاک کردن فیلترها</a>
            </div>
        @endforelse

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $accommodations->appends(request()->query())->links() }}
        </div>
    </div>

    {{-- RIGHT: Map --}}
    <div class="results-right">
        <div id="mapMain" style="width:100%;height:100%;"></div>
    </div>
</div>

{{-- Mobile map toggle --}}
<button class="map-toggle-btn" onclick="toggleMobileMap()">
    <i class="bi bi-map"></i> نمایش روی نقشه
</button>
<div class="mobile-map-overlay" id="mobileMapOverlay">
    <div id="mapMobile" style="width:100%;height:100%;"></div>
    <button class="mobile-map-close" onclick="toggleMobileMap()">✕</button>
</div>

@endsection

@push('scripts')
<script>
// ─── Select2 ──────────────────────────────────────────────────────────────────
$('.select2-basic').select2({ theme: 'bootstrap-5', language: 'fa', width: '100%' });

// ─── Province → City ─────────────────────────────────────────────────────────
$('#provinceSelect').on('change', function() {
    var pid = $(this).val();
    var cs = $('#citySelect');
    cs.prop('disabled', true).html('<option value="">در حال بارگذاری...</option>');
    if (!pid) { cs.html('<option value="">همه شهرها</option>').prop('disabled', false); return; }
    $.getJSON('/api/provinces/' + pid + '/cities', function(data) {
        var opts = '<option value="">همه شهرها</option>';
        data.forEach(function(c) { opts += '<option value="' + c.id + '">' + c.name + '</option>'; });
        cs.html(opts).prop('disabled', false).trigger('change.select2');
    });
});

// ─── Date picker ──────────────────────────────────────────────────────────────
var ciGreg = $('#checkIn').val(), coGreg = $('#checkOut').val();
initJalaliRange('#indexCalEl', '#checkIn', '#checkOut', '#indexDateDisplay', ciGreg, coGreg, function() {
    document.getElementById('filterForm').submit();
});

// ─── Map data ─────────────────────────────────────────────────────────────────
var mapAccs = {!! json_encode($accommodations->map(fn($a) => [
    'id'    => $a->id,
    'name'  => $a->name,
    'price' => number_format($a->price_per_night),
    'lat'   => $a->lat,
    'lng'   => $a->lng,
    'url'   => route('accommodations.show', $a),
])->filter(fn($a) => $a['lat'] && $a['lng'])->values()) !!};

function initMap(containerId) {
    var mapEl = document.getElementById(containerId);
    if (!mapEl || mapEl._leafletMap) return;

    var m = L.map(mapEl, { zoomControl: true }).setView([32.4279, 53.6880], 5);
    mapEl._leafletMap = m;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(m);

    var bounds = [];
    mapAccs.forEach(function(acc) {
        bounds.push([acc.lat, acc.lng]);
        var icon = L.divIcon({
            className: '',
            html: '<div class="leaflet-price-marker">' + acc.price + '</div>',
            iconSize: null,
            iconAnchor: [30, 16],
        });
        var marker = L.marker([acc.lat, acc.lng], { icon: icon }).addTo(m);
        marker.bindPopup(
            '<div style="font-family:Vazirmatn,sans-serif;min-width:160px;">' +
            '<strong style="font-size:13px;">' + acc.name + '</strong><br>' +
            '<span style="font-size:12px;color:#717171;">' + acc.price + ' تومان/شب</span><br>' +
            '<a href="' + acc.url + '" style="color:#FF385C;font-size:12px;text-decoration:underline;">مشاهده اقامتگاه</a>' +
            '</div>',
            { maxWidth: 200 }
        );
    });
    if (bounds.length > 0) {
        m.fitBounds(bounds, { padding: [40, 40] });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initMap('mapMain');
});

// ─── Mobile map toggle ────────────────────────────────────────────────────────
function toggleMobileMap() {
    var overlay = document.getElementById('mobileMapOverlay');
    overlay.classList.toggle('active');
    if (overlay.classList.contains('active')) {
        initMap('mapMobile');
        setTimeout(function() {
            var m = document.getElementById('mapMobile')._leafletMap;
            if (m) m.invalidateSize();
        }, 300);
    }
}

// ─── Wishlist ─────────────────────────────────────────────────────────────────
function toggleWishlist(btn, id) {
    var svg = btn.querySelector('svg');
    var filled = svg.style.fill === 'rgb(255, 56, 92)';
    svg.style.fill = filled ? 'rgba(0,0,0,0.3)' : '#FF385C';
    svg.style.stroke = filled ? '#fff' : '#FF385C';
}
</script>
@endpush
