@extends('layouts.app')

@section('title', 'بنیاد — رزرو اقامتگاه')

@push('styles')
<style>
/* ── Hero search ──────────────────────────────────── */
.home-hero {
    background: linear-gradient(135deg, #fff5f7 0%, #fff 60%);
    padding: 52px 0 40px;
    border-bottom: 1px solid var(--bnb-border);
}
.home-hero-title { font-size: clamp(24px,4vw,40px); font-weight: 700; color: var(--bnb-dark); margin-bottom: 8px; }
.home-hero-sub   { font-size: 16px; color: var(--bnb-gray); margin-bottom: 28px; }

/* Search box */
.home-sb {
    background: #fff;
    border: 1px solid var(--bnb-border);
    border-radius: 16px;
    box-shadow: 0 4px 24px rgba(0,0,0,.12);
    padding: 8px;
    max-width: 860px;
    margin: 0 auto;
}
.home-sb .hf-wrap {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr auto;
    gap: 0;
}
@media (max-width: 767px) {
    .home-sb .hf-wrap { grid-template-columns: 1fr; }
}
.home-sb .hf {
    padding: 12px 16px;
    border: none;
    background: none;
    text-align: right;
    border-left: 1px solid var(--bnb-border);
    cursor: pointer;
}
.home-sb .hf:last-of-type { border-left: none; }
.home-sb .hf:hover { background: var(--bnb-bg-light); border-radius: 12px; }
.hf .hf-label { font-size: 11px; font-weight: 700; letter-spacing: .3px; display: block; margin-bottom: 3px; }
.hf .hf-value { font-size: 13px; color: var(--bnb-gray); display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.home-sb .sb-go {
    background: var(--bnb-red);
    border: none;
    border-radius: 12px;
    padding: 12px 20px;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    font-family: var(--bnb-font);
    cursor: pointer;
    transition: background .15s;
    display: flex; align-items: center; gap: 8px;
    margin: 0;
    white-space: nowrap;
}
.home-sb .sb-go:hover { background: var(--bnb-red-hover); }

/* Expanded inputs */
.home-sb .hf-expanded { display: none; }
.home-sb .hf-expanded.show { display: block; }
.home-sb-bottom { padding: 8px 8px 4px; border-top: 1px solid var(--bnb-border); margin-top: 8px; }

/* Category filter */
.home-cats { padding: 16px 0; }
.home-cats-inner {
    display: flex;
    gap: 0;
    overflow-x: auto;
    scrollbar-width: none;
    padding-bottom: 4px;
}
.home-cats-inner::-webkit-scrollbar { display: none; }
.home-cat {
    display: flex; flex-direction: column; align-items: center; gap: 5px;
    padding: 8px 20px 6px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    color: var(--bnb-gray);
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
    transition: color .15s, border-color .15s;
    text-decoration: none;
    white-space: nowrap;
}
.home-cat.active, .home-cat:hover { color: var(--bnb-dark); border-bottom-color: var(--bnb-dark); }
.home-cat .cat-emoji { font-size: 22px; }

/* Discount ribbon */
.discount-banner {
    background: linear-gradient(135deg, #ff385c 0%, #e31c5f 100%);
    color: #fff;
    border-radius: var(--bnb-radius);
    padding: 20px 24px;
    margin: 24px 0;
}

/* Inline date picker */
.home-datepicker-wrap { position: relative; }
.home-date-display {
    border: 1px solid var(--bnb-border);
    border-radius: var(--bnb-radius-sm);
    padding: 10px 14px;
    cursor: pointer;
    min-height: 42px;
    background: #fff;
    transition: border-color .15s;
}
.home-date-display:hover { border-color: var(--bnb-dark); }
.range-picker-phase { font-size: 12px; color: var(--bnb-gray); margin-top: 4px; }

/* Map */
#map { height: 280px; border-radius: var(--bnb-radius); }
@media (min-width: 768px) { #map { height: 340px; } }
</style>
@endpush

@section('content')

{{-- ═══════════════════════════════════
     HERO + SEARCH
═══════════════════════════════════ --}}
<div class="home-hero">
    <div class="container-fluid px-3 px-lg-5">
        <div class="text-center mb-4">
            <h1 class="home-hero-title">کجا می‌خواهید بروید؟</h1>
            <p class="home-hero-sub">بهترین اقامتگاه‌ها در سراسر ایران را کشف کنید</p>
        </div>

        {{-- Big Search Box --}}
        <div class="home-sb">
            <form action="{{ route('accommodations.index') }}" method="GET" id="searchForm">
                <div class="hf-wrap">
                    {{-- Location --}}
                    <div class="hf" id="hfLocationBtn" style="border-left:none;border-right:1px solid #DDDDDD;">
                        <span class="hf-label">کجا</span>
                        <span class="hf-value" id="hfLocationVal">جستجوی مقصد</span>
                    </div>
                    {{-- Check-in --}}
                    <div class="hf" id="hfCheckinBtn" style="border-left:none;border-right:1px solid #DDDDDD;">
                        <span class="hf-label">ورود</span>
                        <span class="hf-value" id="homeDateDisplay">تاریخ ورود</span>
                    </div>
                    {{-- Checkout --}}
                    <div class="hf" id="hfCheckoutBtn" style="border-left:none;border-right:1px solid #DDDDDD;">
                        <span class="hf-label">خروج</span>
                        <span class="hf-value" id="homeCheckoutDisplay">تاریخ خروج</span>
                    </div>
                    {{-- Submit --}}
                    <div class="d-flex align-items-center p-1">
                        <button type="submit" class="sb-go w-100">
                            <i class="bi bi-search"></i>
                            <span>جستجو</span>
                        </button>
                    </div>
                </div>

                {{-- Expandable fields --}}
                <div class="home-sb-bottom" id="hfExpandedLocation" style="display:none;">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label class="bnb-label">استان</label>
                            <select name="province_id" id="provinceSelect" class="bnb-select select2-basic">
                                <option value="">انتخاب استان</option>
                                @foreach($provinces as $province)
                                    <option value="{{ $province->id }}">{{ $province->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="bnb-label">شهر</label>
                            <select name="city_id" id="citySelect" class="bnb-select select2-basic" disabled>
                                <option value="">ابتدا استان را انتخاب کنید</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="bnb-label">مهمانان</label>
                            <select name="guests" class="bnb-select">
                                @for($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}">{{ $i }} نفر</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                </div>

                <div class="home-sb-bottom" id="hfExpandedDates" style="display:none;">
                    <div class="row g-3 align-items-start">
                        <div class="col-md-5">
                            <div class="range-picker-trigger home-date-display border rounded-3 px-3 py-2 bg-white d-flex align-items-center justify-content-between"
                                 data-bs-toggle="collapse" data-bs-target="#homeDateCal">
                                <div id="homeDateDisplay2"><span class="text-muted">تاریخ ورود</span></div>
                                <i class="bi bi-calendar3" style="color:var(--bnb-red)"></i>
                            </div>
                            <div class="range-picker-phase text-info small mt-1">
                                <i class="bi bi-info-circle me-1"></i>کلیک اول: ورود — کلیک دوم: خروج
                            </div>
                            <div class="collapse mt-1" id="homeDateCal">
                                <div class="range-picker-cal"><div id="homeCalEl"></div></div>
                            </div>
                            <input type="hidden" name="check_in" id="checkIn">
                            <input type="hidden" name="check_out" id="checkOut">
                        </div>
                        <div class="col-md-4">
                            <label class="bnb-label">نوع اقامتگاه</label>
                            <select name="type" class="bnb-select">
                                <option value="">همه انواع</option>
                                <option value="hotel">هتل</option>
                                <option value="villa">ویلا</option>
                                <option value="apartment">آپارتمان</option>
                                <option value="hostel">هاستل</option>
                                <option value="traditional">اقامتگاه سنتی</option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="wheelchair" id="wheelchairFilter" value="1">
                                <label class="form-check-label" for="wheelchairFilter" style="font-size:13px;">
                                    <i class="bi bi-wheelchair me-1"></i>مناسب ویلچر
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Map picker (hidden) --}}
                <div class="home-sb-bottom" id="hfExpandedMap" style="display:none;">
                    <p class="small mb-2" style="color:var(--bnb-gray);"><i class="bi bi-cursor me-1"></i>روی نقشه کلیک کنید تا شهر نزدیک انتخاب شود</p>
                    <div id="map"></div>
                    <input type="hidden" name="map_lat" id="mapLat">
                    <input type="hidden" name="map_lng" id="mapLng">
                    <div id="mapSelectedCity" class="mt-2 small d-none" style="color:var(--bnb-red);">
                        <i class="bi bi-geo-alt-fill me-1"></i> <span id="mapCityLabel"></span>
                    </div>
                </div>
            </form>
        </div>

        {{-- Quick location tabs --}}
        <div class="d-flex justify-content-center gap-3 mt-3 flex-wrap">
            <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="toggleExpanded('location')">
                <i class="bi bi-geo-alt me-1"></i>انتخاب مکان
            </button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="toggleExpanded('dates')">
                <i class="bi bi-calendar me-1"></i>انتخاب تاریخ
            </button>
            <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="toggleExpanded('map')">
                <i class="bi bi-map me-1"></i>نقشه
            </button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════
     CATEGORY FILTERS
═══════════════════════════════════ --}}
<div class="home-cats" style="border-bottom:1px solid var(--bnb-border);">
    <div class="container-fluid px-3 px-lg-5">
        <div class="home-cats-inner">
            @php
            $cats = [
                ['', 'همه', '🏠'],
                ['hotel', 'هتل', '🏨'],
                ['villa', 'ویلا', '🏡'],
                ['apartment', 'آپارتمان', '🏢'],
                ['hostel', 'هاستل', '🛏'],
                ['traditional', 'سنتی', '🏺'],
            ];
            $activeType = request('type', '');
            @endphp
            @foreach($cats as [$val, $label, $icon])
                <a href="{{ route('accommodations.index', array_merge(request()->query(), $val ? ['type' => $val] : ['type' => ''])) }}"
                   class="home-cat {{ $activeType === $val ? 'active' : '' }}">
                    <span class="cat-emoji">{{ $icon }}</span>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════
     DISCOUNT BANNER
═══════════════════════════════════ --}}
<div class="container-fluid px-3 px-lg-5 mt-4">
    <div class="discount-banner" data-aos="fade-up">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h5 class="fw-bold mb-2"><i class="bi bi-shield-check me-2"></i>تخفیف ویژه برای ایثارگران</h5>
                <p class="mb-0 small opacity-90">
                    خانواده شهدا و ایثارگران از ۳۰ تا ۵۰٪ تخفیف ویژه بهره‌مند می‌شوند. برای دریافت تخفیف، کد ملی خود را تأیید کنید.
                </p>
            </div>
            <div class="col-md-4 mt-3 mt-md-0 text-md-start">
                @guest
                    <a href="{{ route('auth.mobile') }}" class="btn btn-light fw-bold" style="border-radius:20px;">
                        ورود و دریافت تخفیف
                    </a>
                @else
                    <a href="{{ route('profile.index') }}" class="btn btn-light fw-bold" style="border-radius:20px;">
                        مشاهده پروفایل
                    </a>
                @endguest
            </div>
        </div>
        <div class="row g-2 mt-3">
            @foreach([
                ['خانواده شهید', '50%'],
                ['جانباز ۷۰٪+', '50%'],
                ['خانواده آزاده', '40%'],
                ['جانباز ۵۰-۶۹٪', '40%'],
                ['جانباز ۲۵-۴۹٪', '30%'],
            ] as [$label, $pct])
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="bg-white bg-opacity-25 rounded-3 px-3 py-2 text-center" style="font-size:12px; backdrop-filter:blur(4px);">
                        <div class="fw-bold" style="font-size:18px;">{{ $pct }}</div>
                        <div style="font-size:11px; opacity:.9;">{{ $label }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════
     FEATURED ACCOMMODATIONS
═══════════════════════════════════ --}}
<div class="container-fluid px-3 px-lg-5 mt-5">
    <div class="bnb-section-header mb-3">
        <h2 class="bnb-section-title">اقامتگاه‌های پیشنهادی</h2>
        <a href="{{ route('accommodations.index') }}" class="bnb-show-all">مشاهده همه</a>
    </div>

    @if($featured->isEmpty())
        <div class="text-center py-5" style="color:var(--bnb-gray);">
            <i class="bi bi-house" style="font-size:48px;display:block;margin-bottom:12px;"></i>
            <p>هنوز اقامتگاهی ثبت نشده است</p>
            <a href="{{ route('accommodations.index') }}" class="btn-bnb" style="display:inline-block;padding:10px 24px;border-radius:8px;text-decoration:none;">جستجوی اقامتگاه</a>
        </div>
    @else
        <div class="bnb-grid">
            @foreach($featured as $acc)
                @php
                    $rating = $acc->averageRating();
                    $rCount = $acc->reviewCount();
                    $imgUrl = $acc->image ? asset('storage/' . $acc->image) : null;
                    $typeLabel = $acc->typeLabel();
                @endphp
                <div data-aos="fade-up" data-aos-delay="{{ ($loop->index % 4) * 60 }}">
                    <a href="{{ route('accommodations.show', $acc) }}" class="text-decoration-none">
                        <div class="bnb-card">
                            <div class="bnb-card-img-wrap">
                                @if($imgUrl)
                                    <img src="{{ $imgUrl }}" alt="{{ $acc->name }}" class="bnb-card-img" loading="lazy">
                                @else
                                    <div class="bnb-card-img-placeholder">
                                        @if($acc->type === 'hotel') 🏨
                                        @elseif($acc->type === 'villa') 🏡
                                        @elseif($acc->type === 'apartment') 🏢
                                        @elseif($acc->type === 'hostel') 🛏
                                        @else 🏠
                                        @endif
                                    </div>
                                @endif
                                {{-- Heart button --}}
                                <button class="bnb-card-heart" onclick="event.preventDefault(); toggleWishlist(this, {{ $acc->id }})" title="ذخیره">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" aria-hidden="true" role="presentation" focusable="false" style="fill:rgba(0,0,0,.5);stroke:#fff;stroke-width:2;overflow:visible;">
                                        <path d="M16 28c7-4.73 14-10 14-17a6.98 6.98 0 0 0-7-7c-1.8 0-3.58.68-4.95 2.05L16 8.1l-2.05-2.05a6.98 6.98 0 0 0-9.9 9.9C5.14 17.31 16 28 16 28z"/>
                                    </svg>
                                </button>
                                {{-- Type badge --}}
                                <div class="bnb-card-badge">{{ $typeLabel }}</div>
                                {{-- Rating --}}
                                @if($rating > 0)
                                    <div class="bnb-card-rating-badge">
                                        <i class="bi bi-star-fill" style="font-size:10px;color:#FFB400;"></i>
                                        {{ $rating }}
                                    </div>
                                @endif
                            </div>
                            <div class="bnb-card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="bnb-card-title flex-grow-1 me-2">{{ $acc->name }}</div>
                                    @if($rating > 0)
                                        <div class="bnb-card-rating flex-shrink-0">
                                            <i class="bi bi-star-fill star"></i>
                                            <span>{{ $rating }}</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="bnb-card-subtitle">
                                    {{ $acc->city->province->name ?? '' }}{{ ($acc->city->province->name ?? '') && ($acc->city->name ?? '') ? '،' : '' }} {{ $acc->city->name ?? '' }}
                                </div>
                                <div class="bnb-card-subtitle">ظرفیت {{ $acc->capacity }} نفر · {{ $acc->rooms }} اتاق</div>
                                <div class="bnb-card-price mt-1">
                                    {{ number_format($acc->price_per_night) }} تومان
                                    <span>/ شب</span>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ═══════════════════════════════════
     POPULAR CITIES
═══════════════════════════════════ --}}
@if($popularCities->isNotEmpty())
<div class="container-fluid px-3 px-lg-5 mt-5 mb-4">
    <h2 class="bnb-section-title mb-4" data-aos="fade-up">مقاصد محبوب</h2>
    <div class="row g-3">
        @foreach($popularCities->take(8) as $city)
            <div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="{{ $loop->index * 50 }}">
                <a href="{{ route('accommodations.index', ['city_id' => $city->id]) }}"
                   class="text-decoration-none">
                    <div style="border:1px solid var(--bnb-border);border-radius:var(--bnb-radius);padding:20px;text-align:center;transition:box-shadow .2s;background:#fff;"
                         onmouseover="this.style.boxShadow='var(--bnb-shadow)'"
                         onmouseout="this.style.boxShadow='none'">
                        <div style="font-size:32px;margin-bottom:8px;">🏙</div>
                        <div style="font-weight:600;color:var(--bnb-dark);font-size:14px;">{{ $city->name }}</div>
                        <div style="font-size:12px;color:var(--bnb-gray);margin-top:2px;">{{ $city->accommodations_count }} اقامتگاه</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
// ─── Toggle expanded search sections ─────────────────────────────────────────
function toggleExpanded(section) {
    var ids = { location: 'hfExpandedLocation', dates: 'hfExpandedDates', map: 'hfExpandedMap' };
    Object.keys(ids).forEach(function(k) {
        var el = document.getElementById(ids[k]);
        if (k === section) {
            el.style.display = el.style.display === 'none' ? 'block' : 'none';
        } else {
            el.style.display = 'none';
        }
    });
}

// ─── Province → City AJAX ─────────────────────────────────────────────────────
$('#provinceSelect').on('change', function() {
    var pid = $(this).val();
    var citySelect = $('#citySelect');
    citySelect.prop('disabled', true).html('<option value="">در حال بارگذاری...</option>');
    if (!pid) { citySelect.html('<option value="">ابتدا استان را انتخاب کنید</option>'); return; }
    $.getJSON('/api/provinces/' + pid + '/cities', function(data) {
        var opts = '<option value="">انتخاب شهر (اختیاری)</option>';
        data.forEach(function(c) { opts += '<option value="' + c.id + '">' + c.name + '</option>'; });
        citySelect.html(opts).prop('disabled', false);
        citySelect.trigger('change.select2');
    });
});

// ─── Select2 ──────────────────────────────────────────────────────────────────
$('.select2-basic').select2({ theme: 'bootstrap-5', language: 'fa', width: '100%' });

// ─── Persian Jalali range picker ──────────────────────────────────────────────
initJalaliRange('#homeCalEl', '#checkIn', '#checkOut', '#homeDateDisplay2');

// ─── Leaflet Map ──────────────────────────────────────────────────────────────
var map = L.map('map').setView([32.4279, 53.6880], 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);

var mapMarker = null;
var cities = {!! json_encode($citiesForMap) !!};

map.on('click', function(e) {
    var lat = e.latlng.lat, lng = e.latlng.lng;
    $('#mapLat').val(lat.toFixed(6)); $('#mapLng').val(lng.toFixed(6));
    if (mapMarker) map.removeLayer(mapMarker);
    mapMarker = L.marker([lat, lng]).addTo(map);

    var nearest = null, minDist = Infinity;
    cities.forEach(function(c) {
        if (!c.lat || !c.lng) return;
        var d = Math.hypot(c.lat - lat, c.lng - lng);
        if (d < minDist) { minDist = d; nearest = c; }
    });
    if (nearest && minDist < 2) {
        $('#mapCityLabel').text(nearest.province + ' - ' + nearest.name);
        $('#mapSelectedCity').removeClass('d-none');
        $('input[name=city_id_map]').remove();
        $('<input>').attr({ type: 'hidden', name: 'city_id', value: nearest.id }).appendTo('#searchForm');
    }
});

// ─── Wishlist (placeholder) ───────────────────────────────────────────────────
function toggleWishlist(btn, id) {
    var svg = btn.querySelector('svg');
    var filled = svg.style.fill === 'rgb(255, 56, 92)';
    svg.style.fill = filled ? 'rgba(0,0,0,0.5)' : '#FF385C';
    svg.style.stroke = filled ? '#fff' : '#FF385C';
}
</script>
@endpush

