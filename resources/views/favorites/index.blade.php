@extends('layouts.app')

@section('title', 'علاقه‌مندی‌های من')

@push('styles')
<style>
/* ══ Favorites page ═══════════════════════════════════ */
.fav-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 32px 24px 80px;
}
@media (max-width: 767px) {
    .fav-page { padding: 20px 16px 90px; }
}

.fav-header {
    margin-bottom: 28px;
}
.fav-title {
    font-size: 26px;
    font-weight: 700;
    color: var(--bnb-dark);
    margin: 0 0 6px;
}
.fav-subtitle {
    font-size: 14px;
    color: var(--bnb-gray);
    margin: 0;
}

/* Grid */
.fav-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
}
@media (max-width: 600px) {
    .fav-grid { grid-template-columns: 1fr; gap: 16px; }
}

/* Card */
.fav-card {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid var(--bnb-border);
    transition: box-shadow .2s, transform .15s;
    position: relative;
}
.fav-card:hover {
    box-shadow: 0 8px 28px rgba(0,0,0,.10);
    transform: translateY(-2px);
}

.fav-img-wrap {
    width: 100%;
    height: 200px;
    overflow: hidden;
    position: relative;
    background: var(--bnb-bg-light);
}
.fav-img-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform .3s ease;
}
.fav-card:hover .fav-img-wrap img {
    transform: scale(1.04);
}
.fav-img-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
}

/* Remove heart button */
.fav-remove-btn {
    position: absolute;
    top: 10px;
    left: 10px;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(255,255,255,.9);
    backdrop-filter: blur(4px);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .15s, transform .15s;
    z-index: 2;
}
.fav-remove-btn:hover {
    background: #fff;
    transform: scale(1.1);
}
.fav-remove-btn svg {
    fill: #FF385C;
    stroke: #FF385C;
    stroke-width: 1.5;
    width: 18px;
    height: 18px;
    overflow: visible;
    transition: transform .2s;
}
.fav-remove-btn:hover svg {
    transform: scale(1.2);
}

/* Card body */
.fav-card-body {
    padding: 14px 16px 16px;
}
.fav-card-location {
    font-size: 11px;
    color: var(--bnb-gray);
    margin-bottom: 3px;
}
.fav-card-name {
    font-size: 16px;
    font-weight: 700;
    color: var(--bnb-dark);
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.fav-card-meta {
    font-size: 12px;
    color: var(--bnb-gray);
    margin-bottom: 10px;
}
.fav-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.fav-card-price {
    font-size: 15px;
    font-weight: 700;
    color: var(--bnb-dark);
}
.fav-card-price span {
    font-size: 11px;
    font-weight: 400;
    color: var(--bnb-gray);
}
.fav-card-rating {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    font-weight: 600;
    color: var(--bnb-dark);
}
.fav-card-rating .star { color: #FFB400; font-size: 11px; }

.fav-card-link {
    position: absolute;
    inset: 0;
    z-index: 1;
}

/* Empty state */
.fav-empty {
    text-align: center;
    padding: 64px 24px;
}
.fav-empty-icon {
    font-size: 64px;
    margin-bottom: 20px;
    display: block;
    line-height: 1;
}
.fav-empty-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--bnb-dark);
    margin-bottom: 10px;
}
.fav-empty-text {
    font-size: 15px;
    color: var(--bnb-gray);
    max-width: 360px;
    margin: 0 auto 24px;
    line-height: 1.6;
}

/* Remove animation */
.fav-card.removing {
    animation: fav-remove .35s ease forwards;
}
@keyframes fav-remove {
    to { opacity: 0; transform: scale(.9) translateY(-8px); }
}
</style>
@endpush

@section('content')
<div class="fav-page">

    {{-- Header --}}
    <div class="fav-header">
        <h1 class="fav-title">علاقه‌مندی‌ها</h1>
        <p class="fav-subtitle">
            @if($favorites->count() > 0)
                {{ $favorites->count() }} اقامتگاه ذخیره شده
            @else
                جایی که دوست دارید بمانید را ذخیره کنید
            @endif
        </p>
    </div>

    @if($favorites->isEmpty())
        {{-- Empty state --}}
        <div class="fav-empty">
            <span class="fav-empty-icon">🤍</span>
            <h2 class="fav-empty-title">هنوز علاقه‌مندی‌ای ندارید</h2>
            <p class="fav-empty-text">
                در حین جستجو روی آیکون قلب روی هر اقامتگاه بزنید تا اینجا ذخیره شود.
            </p>
            <a href="{{ route('accommodations.index') }}" class="btn-bnb" style="display:inline-block;text-decoration:none;">
                <i class="bi bi-search me-1"></i>جستجوی اقامتگاه
            </a>
        </div>
    @else
        <div class="fav-grid" id="favGrid">
            @foreach($favorites as $acc)
                @php
                    $cover  = $acc->image ?: collect($acc->images ?? [])->filter()->first();
                    $rating = $acc->averageRating();
                    $rCount = $acc->reviewCount();
                @endphp
                <div class="fav-card" id="fav-card-{{ $acc->id }}" data-aos="fade-up">
                    {{-- Link overlay --}}
                    <a href="{{ route('accommodations.show', $acc) }}" class="fav-card-link" aria-label="{{ $acc->name }}"></a>

                    {{-- Image --}}
                    <div class="fav-img-wrap">
                        @if($cover)
                            <img src="{{ asset('storage/' . $cover) }}" alt="{{ $acc->name }}" loading="lazy">
                        @else
                            <div class="fav-img-placeholder">
                                @if($acc->type==='hotel') 🏨
                                @elseif($acc->type==='villa') 🏡
                                @elseif($acc->type==='apartment') 🏢
                                @elseif($acc->type==='hostel') 🛏
                                @else 🏠
                                @endif
                            </div>
                        @endif

                        {{-- Remove button --}}
                        <button class="fav-remove-btn"
                                title="حذف از علاقه‌مندی‌ها"
                                onclick="removeFavorite(event, {{ $acc->id }})"
                                style="z-index: 3;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
                                <path d="M16 28c7-4.73 14-10 14-17a6.98 6.98 0 0 0-7-7c-1.8 0-3.58.68-4.95 2.05L16 8.1l-2.05-2.05a6.98 6.98 0 0 0-9.9 9.9C5.14 17.31 16 28 16 28z"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="fav-card-body">
                        <div class="fav-card-location">
                            {{ $acc->typeLabel() }} · {{ $acc->city->province->name ?? '' }}، {{ $acc->city->name ?? '' }}
                        </div>
                        <div class="fav-card-name">{{ $acc->name }}</div>
                        <div class="fav-card-meta">ظرفیت {{ $acc->capacity }} نفر · {{ $acc->rooms }} اتاق</div>
                        <div class="fav-card-footer">
                            <div class="fav-card-price">
                                {{ number_format($acc->price_per_night) }}
                                <span>تومان / شب</span>
                            </div>
                            @if($rating > 0)
                                <div class="fav-card-rating">
                                    <i class="bi bi-star-fill star"></i>
                                    <span>{{ $rating }}</span>
                                    <span style="font-weight:400;color:var(--bnb-gray);">({{ $rCount }})</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
function removeFavorite(e, id) {
    e.preventDefault();
    e.stopPropagation();

    fetch('/favorites/' + id + '/toggle', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            'Accept': 'application/json'
        }
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (!data.favorited) {
            var card = document.getElementById('fav-card-' + id);
            if (card) {
                card.classList.add('removing');
                card.addEventListener('animationend', function() {
                    card.remove();
                    // Update subtitle count
                    var remaining = document.querySelectorAll('#favGrid .fav-card').length;
                    var subtitle = document.querySelector('.fav-subtitle');
                    if (subtitle) {
                        if (remaining === 0) {
                            // Reload to show empty state
                            window.location.reload();
                        } else {
                            subtitle.textContent = remaining + ' اقامتگاه ذخیره شده';
                        }
                    }
                }, { once: true });
            }
        }
    });
}
</script>
@endpush
