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
.home-cats { padding: 16px 0 0; }
.home-cats-inner {
    display: flex;
    gap: 0;
    overflow-x: auto;
    scrollbar-width: none;
    padding-bottom: 0;
    justify-content: center;
    align-items: center;
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

/* Mobile search link - clicking the hero search on mobile opens mobile modal */
@media (max-width: 767px) {
    .home-hero form,
    .home-sb { display: none !important; }
    .bnb-mobile-hero-search-hint {
        display: flex !important;
    }
}
.bnb-mobile-hero-search-hint {
    display: none;
    justify-content: center;
    margin: 12px auto 0;
}
.bnb-mobile-hero-search-hint-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid var(--bnb-border);
    border-radius: 40px;
    padding: 10px 20px;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,.12);
    cursor: pointer;
    font-family: var(--bnb-font);
    font-size: 14px;
    font-weight: 600;
    color: var(--bnb-dark);
}
.bnb-mobile-hero-search-hint-btn .mhsh-icon {
    width: 34px; height: 34px;
    background: var(--bnb-red);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 14px;
    flex-shrink: 0;
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
@php
    $isVeteran = auth()->check() && auth()->user()->veteran_type && auth()->user()->veteran_type !== 'none';
    $isRegularUser = auth()->check() && !$isVeteran;
@endphp

@if(!$isRegularUser)
<div class="container-fluid px-3 px-lg-5 mt-4">
    @if($isVeteran)
        @php $user = auth()->user(); @endphp
        <div class="d-flex align-items-center bg-white rounded-4 shadow-sm position-relative mb-4 overflow-hidden" 
             style="min-height: 90px; border: 1px solid var(--bnb-border);">
            
            {{-- Coupon Value Side --}}
            <div class="d-flex flex-column align-items-center justify-content-center p-3 position-relative" 
                 style="background: #fff; min-width: 100px; border-left: 1px dashed var(--bnb-border); height: 90px;">
                
                {{-- Minimal Ticket Punches (Top/Bottom Center of Divider) --}}
                <div style="position: absolute; top: -10px; left: -10px; width: 20px; height: 20px; background: var(--bnb-bg); border-radius: 50%; border: 1px solid var(--bnb-border); z-index: 5;"></div>
                <div style="position: absolute; bottom: -10px; left: -10px; width: 20px; height: 20px; background: var(--bnb-bg); border-radius: 50%; border: 1px solid var(--bnb-border); z-index: 5;"></div>

                <div style="font-size: 26px; font-weight: 800; color: var(--bnb-red); line-height: 1;">{{ $user->discount_percentage }}٪</div>
                <div style="font-size: 10px; font-weight: 700; color: var(--bnb-gray); margin-top: 2px;">تخفیف</div>
            </div>

            {{-- Body Side --}}
            <div class="p-3 flex-grow-1 d-flex flex-column justify-content-center">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold text-dark mb-1" style="font-size: 16px;">{{ $user->veteranLabel() }}</div>
                        <div class="text-muted small" style="font-size: 12px;">تخفیف شما به صورت هوشمند در رزرو اعمال می‌شود.</div>
                    </div>
                    @if($user->national_id_verified_at)
                        <div class="d-none d-md-block text-success small fw-bold">
                            <i class="bi bi-patch-check-fill me-1"></i>
                            هویت تأیید شده
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="discount-banner" data-aos="fade-up">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="fw-bold mb-2"><i class="bi bi-shield-check me-2"></i>تخفیف ویژه برای ایثارگران</h5>
                    <p class="mb-0 small opacity-90">
                        خانواده شهدا و ایثارگران از ۳۰ تا ۵۰٪ تخفیف ویژه بهره‌مند می‌شوند. برای دریافت تخفیف، کد ملی خود را تأیید کنید.
                    </p>
                </div>
                <div class="col-md-4 mt-3 mt-md-0 text-md-end">
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
            <div class="row g-2 mt-3 justify-content-center">
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
    @endif
</div>
@endif

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
                                <button class="bnb-card-heart" data-acc-id="{{ $acc->id }}" onclick="event.preventDefault(); toggleWishlist(this, {{ $acc->id }})" title="ذخیره">
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
                                    <span class="text-xs text-slate-500">شروع از</span>
                                    @auth
                                        @if(Auth::user()->discount_percentage > 0)
                                            @php $discHome = round($acc->lowest_price * (1 - Auth::user()->discount_percentage / 100)); @endphp
                                            <span style="font-size:12px;text-decoration:line-through;color:var(--bnb-gray);font-weight:400;">{{ number_format($acc->lowest_price) }}</span>
                                            <span class="font-bold text-lg" style="color:var(--bnb-red);">{{ number_format($discHome) }}</span>
                                        @else
                                            <span class="font-bold text-lg">{{ number_format($acc->lowest_price) }}</span>
                                        @endif
                                    @else
                                        <span class="font-bold text-lg">{{ number_format($acc->lowest_price) }}</span>
                                    @endauth
                                    <span class="text-xs">تومان</span>
                                    <span class="text-slate-400">/ شب</span>
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
if (document.getElementById('map')) {
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
} // end map guard

// ─── Wishlist ───────────────────────────────────────────────────────────────
@auth
var _userFavorites = @json(Auth::user()->favorites()->pluck('accommodations.id')->toArray());
@else
var _userFavorites = [];
@endauth

function setHeartState(btn, favorited) {
    var svg = btn.querySelector('svg');
    if (!svg) return;
    svg.style.fill   = favorited ? '#FF385C' : 'rgba(0,0,0,0.5)';
    svg.style.stroke = favorited ? '#FF385C' : '#fff';
}

document.querySelectorAll('[data-acc-id]').forEach(function (btn) {
    setHeartState(btn, _userFavorites.includes(parseInt(btn.dataset.accId)));
});

function toggleWishlist(btn, id) {
    @guest
    window.location.href = '{{ route('auth.mobile') }}';
    return;
    @endguest

    fetch('{{ url('/favorites') }}/' + id + '/toggle', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            'Accept': 'application/json'
        }
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        setHeartState(btn, data.favorited);
        if (data.favorited) {
            _userFavorites.push(id);
        } else {
            _userFavorites = _userFavorites.filter(function(x){ return x !== id; });
        }
    });
}
</script>
@endpush

