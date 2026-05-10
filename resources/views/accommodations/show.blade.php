@extends('layouts.app')

@section('title', $accommodation->name)

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/swiper/swiper-bundle.min.css') }}" />
<style>
/* ── Photo grid ─────────────────────────────────────── */
.bnb-photo-grid {
    display: grid;
    gap: 8px;
    border-radius: var(--bnb-radius);
    overflow: hidden;
    margin: 0 -24px;
    height: 450px;
}

/* 1 Image Layout */
.bnb-photo-grid.grid-count-1 {
    grid-template-columns: 1fr;
}

/* 2 Images Layout */
.bnb-photo-grid.grid-count-2 {
    grid-template-columns: 1fr 1fr;
}

/* 3 Images Layout */
.bnb-photo-grid.grid-count-3 {
    grid-template-columns: 2fr 1fr;
    grid-template-rows: 1fr 1fr;
}
.grid-count-3 .bnb-photo-grid-main { grid-row: 1 / 3; }

/* 4 Images Layout */
.bnb-photo-grid.grid-count-4 {
    grid-template-columns: 2fr 1fr;
    grid-template-rows: repeat(3, 1fr);
}
.grid-count-4 .bnb-photo-grid-main { grid-row: 1 / 4; }

/* 5 Images Layout */
.bnb-photo-grid.grid-count-5:not(.mobile-grid) {
    grid-template-columns: 2fr 1fr 1fr;
    grid-template-rows: 1fr 1fr;
}
.grid-count-5:not(.mobile-grid) .bnb-photo-grid-main { grid-column: 1 / 2; grid-row: 1 / 3; }

/* 5 Images Layout */
.bnb-photo-grid.grid-count-5:not(.mobile-grid) {
    grid-template-columns: 2fr 1fr 1fr;
    grid-template-rows: 1fr 1fr;
}
.grid-count-5:not(.mobile-grid) .bnb-photo-grid-main { grid-column: 1 / 2; grid-row: 1 / 3; }

/* 6 Images Layout */
.bnb-photo-grid.grid-count-6:not(.mobile-grid) {
    grid-template-columns: 2fr 1fr 1fr 1fr;
    grid-template-rows: 1fr 1fr;
}
.grid-count-6:not(.mobile-grid) .bnb-photo-grid-main { grid-column: 1 / 2; grid-row: 1 / 3; }
.grid-count-6:not(.mobile-grid) .bnb-photo-grid-wrap:nth-child(2) { grid-column: 2 / 3; grid-row: 1 / 3; }

/* 7 Images Layout */
.bnb-photo-grid.grid-count-7:not(.mobile-grid) {
    grid-template-columns: 2fr 1fr 1fr 1fr;
    grid-template-rows: 1fr 1fr;
}
.grid-count-7:not(.mobile-grid) .bnb-photo-grid-main { grid-column: 1 / 2; grid-row: 1 / 3; }

/* 8 Images Layout */
.bnb-photo-grid.grid-count-8:not(.mobile-grid) {
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
    grid-template-rows: 1fr 1fr;
}
.grid-count-8:not(.mobile-grid) .bnb-photo-grid-main { grid-column: 1 / 2; grid-row: 1 / 3; }
.grid-count-8:not(.mobile-grid) .bnb-photo-grid-wrap:nth-child(2) { grid-column: 2 / 3; grid-row: 1 / 3; }

.bnb-photo-grid img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .3s; cursor: pointer;
}
.bnb-photo-grid img:hover { transform: scale(1.02); }
.bnb-photo-grid-wrap { position: relative; overflow: hidden; height: 100%; }
.bnb-show-all-btn {
    position: absolute; bottom: 16px; left: 16px;
    background: #fff; border: 1px solid var(--bnb-border); border-radius: 8px;
    padding: 8px 16px; font-size: 13px; font-weight: 600; cursor: pointer;
    font-family: var(--bnb-font); display: flex; align-items: center; gap: 6px;
}
.bnb-show-all-btn:hover { background: var(--bnb-bg-light); }
@media (max-width: 767px) {
    .bnb-photo-grid { 
        display: none !important;
    }
}
.bnb-mobile-slider { display: none; }
@media (max-width: 767px) {
    .bnb-mobile-slider { 
        display: block; 
        margin: 0 -16px; 
        position: relative;
        margin-top: -64px; /* Pull up to touch the ceiling under transparent nav */
    }
    .bnb-mobile-slider .swiper-slide img {
        width: 100%;
        height: 300px;
        object-fit: cover;
    }
    .bnb-mobile-slider .swiper-pagination-bullet-active {
        background: #fff;
    }
}
/* ── Page layout ─────────────────────────────────────── */
.bnb-show-layout { display: grid; grid-template-columns: 1fr; padding-top: 28px; }
.bnb-book-price { font-size: 20px; font-weight: 700; color: var(--bnb-dark); margin-bottom: 16px; }
.bnb-book-price span { font-size: 14px; font-weight: 400; color: var(--bnb-gray); }
.bnb-book-fields { border: 1px solid var(--bnb-border); border-radius: 8px; overflow: hidden; margin-bottom: 12px; }
.bnb-book-field { padding: 10px 14px; border-bottom: 1px solid var(--bnb-border); }
.bnb-book-field:last-child { border-bottom: none; }
.bnb-book-field label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--bnb-dark); display: block; margin-bottom: 2px; }
.bnb-book-field select, .bnb-book-field .picker-trigger { font-size: 14px; color: var(--bnb-dark); background: none; border: none; outline: none; width: 100%; cursor: pointer; font-family: var(--bnb-font); padding: 0; }
.bnb-price-breakdown { font-size: 14px; color: var(--bnb-dark); margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--bnb-border); }
.bnb-price-row { display: flex; justify-content: space-between; margin-bottom: 8px; }
.bnb-price-row.total { font-weight: 700; padding-top: 12px; border-top: 1px solid var(--bnb-border); font-size: 15px; }
/* ── Host row ─────────────────────────────────────────── */
.bnb-host-row { display: flex; align-items: center; gap: 12px; padding: 20px 0; border-bottom: 1px solid var(--bnb-border); }
.bnb-host-avatar { width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, var(--bnb-red), #E31C5F); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 18px; flex-shrink: 0; }
/* ── Sticky tabs ─────────────────────────────────────── */
.bnb-sticky-tabs { 
    position: sticky; 
    background: #fff; 
    border-bottom: 1px solid var(--bnb-border); 
    z-index: 90; 
    margin: 0 -24px; 
    padding: 0 24px; 
    display: flex; 
    gap: 0; 
    overflow-x: auto; 
    scrollbar-width: none; 
}
/* This class is now handled globally in app.blade.php for perfect sync */
.bnb-sticky-tabs.is-nav-sticky {
    /* Base style inherited from .is-nav-sticky */
}
.bnb-sticky-tabs::-webkit-scrollbar { display: none; }
.bnb-sticky-tab { padding: 14px 16px; font-size: 14px; font-weight: 500; color: var(--bnb-gray); border-bottom: 2px solid transparent; cursor: pointer; white-space: nowrap; text-decoration: none; transition: color .15s, border-color .15s; }
.bnb-sticky-tab:hover, .bnb-sticky-tab.active { color: var(--bnb-dark); border-bottom-color: var(--bnb-dark); }
/* ── Amenities grid ──────────────────────────────────── */
.bnb-amenities-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
.bnb-amenity-item { display: flex; align-items: center; gap: 12px; font-size: 14px; color: var(--bnb-dark); }
/* ── Room card ────────────────────────────────────────── */
.bnb-room-card { border: 1px solid var(--bnb-border); border-radius: 12px; overflow: hidden; margin-bottom: 16px; }
.bnb-rate-row { display: flex; flex-direction: column; gap: 16px; padding: 16px 20px; border-bottom: 1px solid var(--bnb-border); }
@media (min-width: 576px) {
    .bnb-rate-row { display: grid; grid-template-columns: 1fr auto auto; align-items: center; }
}
.bnb-rate-row:last-child { border-bottom: none; }
.bnb-rate-action { display: flex; justify-content: flex-end; }
@media (min-width: 576px) {
    .bnb-rate-action { display: block; }
}
/* ── Star selector ────────────────────────────────────── */
.bnb-star-selector { display: flex; gap: 4px; font-size: 24px; cursor: pointer; }
.bnb-star-selector .bi { color: #ffc107; transition: color .15s; }
/* ── Title and Rating Section ────────────────────────── */
.bnb-main-title { font-size: 26px; font-weight: 800; color: var(--bnb-dark); margin-bottom: 8px; letter-spacing: -0.5px; }
.bnb-meta-row { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; font-size: 14px; color: var(--bnb-dark); }
.bnb-rating-badge { display: flex; align-items: center; gap: 4px; font-weight: 700; }
.bnb-rating-count { color: var(--bnb-gray); font-weight: 400; text-decoration: underline; cursor: pointer; }
.bnb-dot-separator { color: var(--bnb-gray); font-size: 8px; padding: 0 4px; }
.bnb-location-link { color: var(--bnb-gray); text-decoration: underline; font-weight: 500; transition: color 0.2s; }
.bnb-location-link:hover { color: var(--bnb-dark); }
</style>
<link rel="stylesheet" href="{{ asset('vendor/photoswipe/photoswipe.css') }}">
<style>
/* ── Availability Calendar Enhancements ──────────────── */
.cal-unavailable {
    position: relative;
    background: #fafafa !important;
    color: #ccc !important;
    cursor: not-allowed !important;
    overflow: hidden;
}
.cal-unavailable::before {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        -45deg,
        transparent,
        transparent 3px,
        rgba(0,0,0,0.08) 3px,
        rgba(0,0,0,0.08) 4px
    );
    pointer-events: none;
}
.cal-blocked {
    position: relative;
    background: #fff0f0 !important;
    color: #e2756a !important;
    cursor: not-allowed !important;
    overflow: hidden;
}
.cal-blocked::before {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        -45deg,
        transparent,
        transparent 3px,
        rgba(220,38,38,0.12) 3px,
        rgba(220,38,38,0.12) 4px
    );
    pointer-events: none;
}
.cal-low-avail .avail-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #f59e0b;
    margin: 1px auto 0;
}
.cal-avail .avail-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #10b981;
    margin: 1px auto 0;
}
.bnb-avail-legend {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 11px;
    color: var(--bnb-gray);
    margin-bottom: 10px;
    padding: 8px 10px;
    background: #fafafa;
    border-radius: 8px;
}
.bnb-avail-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
}
.bnb-legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 2px;
    flex-shrink: 0;
}
.bnb-avail-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    color: var(--bnb-gray);
    font-size: 13px;
    gap: 8px;
}
@keyframes bnb-spin { to { transform: rotate(360deg); } }
.bnb-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid var(--bnb-border);
    border-top-color: var(--bnb-red);
    border-radius: 50%;
    animation: bnb-spin 0.7s linear infinite;
    flex-shrink: 0;
}
</style>
@endpush

@section('content')
@php
    $allImages = collect($accommodation->images ?? [])->filter()->values();
    if ($accommodation->image && !$allImages->contains($accommodation->image)) {
        $allImages->prepend($accommodation->image);
    }
    $imageSizes = [];
    foreach ($allImages as $img) {
        try {
            $path = \Illuminate\Support\Facades\Storage::disk('public')->path($img);
            if (file_exists($path)) {
                $size = @getimagesize($path);
                if ($size) {
                    $imageSizes[$img] = ['width' => $size[0], 'height' => $size[1]];
                }
            }
        } catch (\Throwable $e) {
            // Fallback to default dimensions below.
        }
    }
    $displayImages = $allImages->take(8);
    $imgCount = $displayImages->count();
@endphp

<div class="container-xxl px-3 px-lg-4">

{{-- MOBILE SLIDER (Swiper) --}}
<div class="bnb-mobile-slider swiper" id="mobile-zoom-gallery">
    <div class="swiper-wrapper">
        @foreach($allImages as $img)
            <div class="swiper-slide">
                <a href="{{ asset('storage/' . $img) }}" 
                   data-pswp-width="{{ $imageSizes[$img]['width'] ?? 1200 }}" 
                   data-pswp-height="{{ $imageSizes[$img]['height'] ?? 800 }}" 
                   class="pswp-gallery-item-mobile"
                   onclick="event.preventDefault();">
                    <img src="{{ asset('storage/' . $img) }}" alt="{{ $accommodation->name }}">
                </a>
            </div>
        @endforeach
    </div>
    <div class="swiper-pagination"></div>
</div>

{{-- PHOTO GRID --}}
<div class="d-none d-md-block" style="position:relative;padding-top:24px;">
    <div class="bnb-photo-grid grid-count-{{ $imgCount }}" id="zoom-gallery">
        @foreach($allImages as $index => $img)
            <div class="bnb-photo-grid-wrap {{ $index === 0 ? 'bnb-photo-grid-main' : '' }} {{ $index >= 8 ? 'd-none' : '' }}">
                <a href="{{ asset('storage/' . $img) }}" 
                   data-pswp-width="{{ $imageSizes[$img]['width'] ?? 1200 }}" 
                   data-pswp-height="{{ $imageSizes[$img]['height'] ?? 800 }}" 
                   class="pswp-gallery-item"
                   onclick="event.preventDefault();">
                    <img src="{{ asset('storage/' . $img) }}" alt="{{ $accommodation->name }}">
                </a>
            </div>
        @endforeach

        @if($imgCount === 0)
            <div class="bnb-photo-grid-wrap bnb-photo-grid-main">
                <div style="height:100%;background:var(--bnb-bg-light);display:flex;align-items:center;justify-content:center;font-size:5rem;">🏠</div>
            </div>
        @endif
    </div>
    @if($allImages->count() > 0)
    <button class="bnb-show-all-btn" id="pswp-btn-all">
        <i class="bi bi-grid-3x3-gap"></i> نمایش همه تصاویر ({{ $allImages->count() }})
    </button>
    @endif
</div>

{{-- TITLE ROW --}}
<div class="d-flex justify-content-between align-items-start mt-4 mb-2" style="flex-wrap:wrap;gap:16px;">
    <div>
        <h1 class="bnb-main-title">{{ $accommodation->name }}</h1>
        <div class="bnb-meta-row">
            @if($accommodation->averageRating() > 0)
                <div class="bnb-rating-badge">
                    <i class="bi bi-star-fill" style="color:var(--bnb-dark); font-size: 13px;"></i>
                    <span>{{ number_format($accommodation->averageRating(), 1) }}</span>
                </div>
                <span class="bnb-rating-count" onclick="document.getElementById('sec-reviews').scrollIntoView({behavior:'smooth'})">
                    {{ $accommodation->reviewCount() }} نظر
                </span>
                <span class="bnb-dot-separator">●</span>
            @endif
            
            <i class="bi bi-geo-alt-fill" style="font-size: 14px; color: var(--bnb-gray);"></i>
            <span style="font-weight: 600;">{{ $accommodation->city->province->name ?? '' }}، {{ $accommodation->city->name ?? '' }}</span>
            
            @if($accommodation->address)
                <span class="bnb-dot-separator">●</span>
                <a href="#sec-map" class="bnb-location-link">{{ $accommodation->address }}</a>
            @endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="bnb-filter-pill" onclick="toggleWishlist(this, {{ $accommodation->id }})"><i class="bi bi-heart me-1"></i>ذخیره</button>
        <button class="bnb-filter-pill" onclick="if(navigator.share) navigator.share({title:'{{ $accommodation->name }}',url:window.location.href})"><i class="bi bi-share me-1"></i>اشتراک</button>
    </div>
</div>

{{-- STICKY TABS --}}
<div class="bnb-sticky-tabs is-nav-sticky" id="stickyTabs">
    <a class="bnb-sticky-tab active" href="#sec-info">اطلاعات</a>
    <a class="bnb-sticky-tab" href="#sec-amenities">امکانات</a>
    @if($roomTypes->isNotEmpty())<a class="bnb-sticky-tab" href="#sec-rooms">اتاق‌ها</a>@endif
    <a class="bnb-sticky-tab" href="#sec-reviews">نظرات</a>
    @if($accommodation->lat && $accommodation->lng)<a class="bnb-sticky-tab" href="#sec-map">موقعیت</a>@endif
</div>

{{-- MAIN LAYOUT --}}
<div class="bnb-show-layout">

    {{-- LEFT --}}
    <div class="bnb-show-left">

        {{-- Spec row --}}
        <div id="sec-info" class="p-3 border rounded-4 d-flex gap-4 my-4" style="flex-wrap:wrap; background-color: #fafafa;">
            <div class="px-3" style="text-align:center;min-width:80px; flex: 1 0 0;"><i class="bi bi-people-fill" style="font-size:1.5rem;"></i><div style="font-size:14px;font-weight:600;margin-top:4px;">{{ $accommodation->capacity }} نفر</div><div style="font-size:12px;color:var(--bnb-gray);">ظرفیت</div></div>
            <div class="px-3" style="text-align:center;min-width:80px; flex: 1 0 0;"><i class="bi bi-door-open-fill" style="font-size:1.5rem;"></i><div style="font-size:14px;font-weight:600;margin-top:4px;">{{ $accommodation->rooms }}</div><div style="font-size:12px;color:var(--bnb-gray);">اتاق</div></div>
            <div class="px-3" style="text-align:center;min-width:80px; flex: 1 0 0;"><i class="bi bi-building" style="font-size:1.5rem;"></i><div style="font-size:14px;font-weight:600;margin-top:4px;">{{ $accommodation->typeLabel() }}</div><div style="font-size:12px;color:var(--bnb-gray);">نوع</div></div>
            @if(in_array('مناسب ویلچر', $accommodation->amenities ?? []))
            <div class="px-3" style="text-align:center;min-width:80px; flex: 1 0 0;"><i class="bi bi-person-wheelchair" style="font-size:1.5rem;color:var(--bnb-red);"></i><div style="font-size:14px;font-weight:600;margin-top:4px;">دسترسی</div><div style="font-size:12px;color:var(--bnb-gray);">ویلچر</div></div>
            @endif
        </div>

        {{-- Host --}}
        <div class="bnb-host-row">
            <div class="bnb-host-avatar">{{ mb_substr($accommodation->host->name ?? 'م', 0, 1) }}</div>
            <div><div style="font-size:15px;font-weight:600;color:var(--bnb-dark);">میزبان: {{ $accommodation->host->name ?? 'بنیاد' }}</div><div style="font-size:13px;color:var(--bnb-gray);">میزبان اقامتگاه شما</div></div>
        </div>

        {{-- Description --}}
        @if($accommodation->description)
        <div class="py-5 border-bottom">
            <div id="descShort" style="font-size:15px;line-height:1.8;color:var(--bnb-dark);">{{ \Illuminate\Support\Str::limit($accommodation->description, 300) }}</div>
            @if(mb_strlen($accommodation->description) > 300)
            <div id="descFull" style="font-size:15px;line-height:1.8;color:var(--bnb-dark);display:none;">{{ $accommodation->description }}</div>
            <button onclick="document.getElementById('descFull').style.display='block';document.getElementById('descShort').style.display='none';this.style.display='none';" style="background:none;border:none;padding:0;margin-top:12px;font-size:14px;font-weight:600;color:var(--bnb-dark);cursor:pointer;text-decoration:underline;font-family:var(--bnb-font);">نمایش بیشتر <i class="bi bi-chevron-down"></i></button>
            @endif
        </div>
        @endif

        {{-- Amenities --}}
        @if($accommodation->amenities && count($accommodation->amenities))
        <div class="py-5 border-bottom" id="sec-amenities">
            <h2 style="font-size:18px;font-weight:700;color:var(--bnb-dark);margin-bottom:20px;">امکانات اقامتگاه</h2>
            <div class="bnb-amenities-grid">
                @foreach(array_slice($accommodation->amenities, 0, 10) as $amenity)
                <div class="bnb-amenity-item"><i class="bi bi-check2-circle" style="color:var(--bnb-dark);font-size:18px;flex-shrink:0;"></i><span>{{ $amenity }}</span></div>
                @endforeach
            </div>
            @if(count($accommodation->amenities) > 10)
            <button onclick="document.getElementById('amenitiesAll').style.display='block';this.style.display='none';" style="margin-top:16px;background:none;border:1px solid var(--bnb-dark);border-radius:8px;padding:12px 24px;font-size:14px;font-weight:600;cursor:pointer;font-family:var(--bnb-font);">نمایش همه {{ count($accommodation->amenities) }} امکانات</button>
            <div id="amenitiesAll" style="display:none;" class="bnb-amenities-grid mt-3">
                @foreach(array_slice($accommodation->amenities, 10) as $amenity)
                <div class="bnb-amenity-item"><i class="bi bi-check2-circle" style="color:var(--bnb-dark);font-size:18px;flex-shrink:0;"></i><span>{{ $amenity }}</span></div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        {{-- Room Types --}}
        @if($roomTypes->isNotEmpty())
        <div class="py-5 border-bottom" id="sec-rooms">
            <h2 style="font-size:18px;font-weight:700;color:var(--bnb-dark);margin-bottom:8px;">انتخاب اتاق</h2>
            <p style="font-size:13px;color:var(--bnb-gray);margin-bottom:20px;">پس از انتخاب تاریخ در ویجت رزرو، اتاق موردنظر را رزرو کنید.</p>
            @foreach($roomTypes as $roomType)
            @if($roomType->rates->isNotEmpty())
            <div class="bnb-room-card" data-aos="fade-up">
                <div style="display:flex;gap:16px;padding:20px;border-bottom:1px solid var(--bnb-border);flex-wrap:wrap;">
                    @php $rtImages = collect($roomType->images ?? [])->filter()->values(); @endphp
                    @if($rtImages->count() > 0)
                    <img src="{{ asset('storage/' . $rtImages[0]) }}" alt="{{ $roomType->name }}" style="width:140px;height:100px;object-fit:cover;border-radius:8px;flex-shrink:0;">
                    @endif
                    <div>
                        <div style="font-size:15px;font-weight:600;color:var(--bnb-dark);margin-bottom:8px;" data-rt-name="{{ $roomType->name }}">{{ $roomType->name }}</div>
                        <div style="font-size:13px;color:var(--bnb-gray);display:flex;flex-wrap:wrap;gap:12px;" data-rt-cap="{{ $roomType->capacity }}">
                            @if($roomType->bed_type)<span><i class="bi bi-moon-stars me-1"></i>{{ $roomType->bed_type }}</span>@endif
                            <span><i class="bi bi-people me-1"></i>{{ $roomType->capacity }} نفر</span>
                            @if($roomType->size_sqm)<span><i class="bi bi-aspect-ratio me-1"></i>{{ $roomType->size_sqm }} متر مربع</span>@endif
                            <span>@if($roomType->has_private_bathroom)<i class="bi bi-check-circle-fill text-success me-1"></i>حمام اختصاصی@else<i class="bi bi-x-circle text-muted me-1"></i>بدون حمام اختصاصی@endif</span>
                        </div>
                    </div>
                </div>
                @foreach($roomType->rates as $rate)
                <div class="bnb-rate-row">
                    <div>
                        <div style="font-size:14px;font-weight:600;color:var(--bnb-dark);margin-bottom:4px;">{{ $rate->name }}</div>
                        <div style="font-size:12px;color:var(--bnb-gray);display:flex;flex-wrap:wrap;gap:8px;">
                            @if($rate->breakfast_included)<span style="color:green"><i class="bi bi-cup-hot-fill me-1"></i>صبحانه رایگان</span>
                            @elseif($rate->breakfast_price_per_person)<span><i class="bi bi-cup-hot me-1"></i>صبحانه {{ number_format($rate->breakfast_price_per_person) }} تومان/نفر</span>
                            @else<span><i class="bi bi-x me-1"></i>بدون صبحانه</span>@endif
                            @if($rate->cancellation_policy === 'free')<span style="color:green"><i class="bi bi-check-circle-fill me-1"></i>لغو رایگان</span>
                            @else<span style="color:var(--bnb-red)"><i class="bi bi-x-circle me-1"></i>غیرقابل استرداد</span>@endif
                            @if($rate->payment_type === 'pay_at_hotel')<span><i class="bi bi-building me-1"></i>پرداخت در محل</span>
                            @else<span><i class="bi bi-credit-card me-1"></i>پرداخت آنلاین</span>@endif
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div style="text-align:right;white-space:nowrap;">
                            @auth
                                @if(Auth::user()->discount_percentage > 0)
                                    @php $discRate = round($rate->price_per_night * (1 - Auth::user()->discount_percentage / 100)); @endphp
                                    <div style="font-size:12px;text-decoration:line-through;color:var(--bnb-gray);">{{ number_format($rate->price_per_night) }}</div>
                                    <div style="font-size:16px;font-weight:700;color:var(--bnb-red);">{{ number_format($discRate) }}</div>
                                @else
                                    <div style="font-size:16px;font-weight:700;color:var(--bnb-dark);">{{ number_format($rate->price_per_night) }}</div>
                                @endif
                            @else
                                <div style="font-size:16px;font-weight:700;color:var(--bnb-dark);">{{ number_format($rate->price_per_night) }}</div>
                            @endauth
                            <div style="font-size:11px;color:var(--bnb-gray);">تومان / شب</div>
                        </div>
                        <div class="bnb-rate-action">
                            @auth
                            <form class="room-reserve-form" action="{{ route('bookings.store', $accommodation) }}" method="POST">
                                @csrf
                                <input type="hidden" name="room_type_id" value="{{ $roomType->id }}">
                                <input type="hidden" name="room_rate_id" value="{{ $rate->id }}">
                                <input type="hidden" name="check_in" class="rt-check-in">
                                <input type="hidden" name="check_out" class="rt-check-out">
                                <input type="hidden" name="guests" value="{{ $roomType->capacity }}">
                                @php
                                    $btnDiscPrice = Auth::user()->discount_percentage > 0 ? round($rate->price_per_night * (1 - Auth::user()->discount_percentage / 100)) : $rate->price_per_night;
                                    $btnOrigPrice = $rate->price_per_night;
                                @endphp
                                <button type="button" class="btn-bnb" style="white-space:nowrap; width: 100%; min-width: 100px;" onclick="reserveRoom(this, {{ $btnDiscPrice }}, {{ $btnOrigPrice }}, {{ $roomType->id }})">رزرو</button>
                            </form>
                            @else
                            <a href="{{ route('auth.mobile') }}" class="btn-bnb" style="text-decoration:none;display:block;white-space:nowrap; width: 100%; text-align: center;">ورود برای رزرو</a>
                            @endauth
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
            @endforeach
        </div>
        @endif

        {{-- Reviews --}}
        <div class="py-5" id="sec-reviews">
            @if($accommodation->averageRating() > 0)
            <div class="d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-star-fill text-warning fs-4"></i>
                <span class="fs-4 fw-bold text-dark">{{ $accommodation->averageRating() }}</span>
                <span class="text-secondary">({{ $accommodation->reviewCount() }} نظر)</span>
            </div>
            @endif
            
            <h2 class="h5 fw-bold text-dark mb-4">نظرات مهمانان</h2>
            
            @if(session('status'))<div class="alert alert-success border-0 shadow-sm mb-4">{{ session('status') }}</div>@endif
            @if($errors->has('rating'))<div class="alert alert-danger border-0 shadow-sm mb-4">{{ $errors->first('rating') }}</div>@endif
            
            @auth
                @if($canReview && !$userReview)
                <div class="card border-0 shadow-sm mb-5 rounded-4 overflow-hidden">
                    <div class="card-body p-4">
                        <h3 class="h6 fw-bold mb-3">ثبت نظر جدید</h3>
                        <form action="{{ route('reviews.store', $accommodation) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="small fw-bold d-block mb-2">امتیاز شما</label>
                                <div class="bnb-star-selector" id="starSelector">
                                    @for($r=1;$r<=5;$r++)<i class="bi bi-star" data-val="{{ $r }}"></i>@endfor
                                </div>
                                <input type="hidden" name="rating" id="ratingInput" value="5">
                            </div>
                            <div class="mb-3">
                                <label class="small fw-bold d-block mb-2">تجربه شما</label>
                                <textarea name="comment" class="form-control border-light-subtle rounded-3" rows="3" placeholder="تجربه اقامت خود را با دیگران به اشتراک بگذارید...">{{ old('comment') }}</textarea>
                                @error('comment')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-dark rounded-pill px-4">ثبت نظر</button>
                        </form>
                    </div>
                </div>
                @elseif($userReview)
                <div class="alert alert-light border shadow-sm mb-4 rounded-3">
                    <i class="bi bi-check-circle-fill text-success me-2"></i>شما قبلاً نظر خود را ثبت کرده‌اید.
                </div>
                @endif
            @endauth

            <div class="row g-4">
                @forelse($reviews as $review)
                <div class="col-12 col-md-6" data-aos="fade-up">
                    <div class="card h-100 border-0 shadow-sm rounded-4">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="flex-shrink-0 bg-light text-dark fw-bold rounded-circle d-flex align-items-center justify-content-center" style="width:48px; height:48px; font-size: 1.1rem;">
                                        {{ mb_substr($review->user->name ?? '؟', 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark mb-0">{{ $review->user->name ?? 'مهمان' }}</div>
                                        <div class="small text-secondary">{{ \Morilog\Jalali\Jalalian::fromCarbon($review->created_at)->ago() }}</div>
                                    </div>
                                </div>
                                <div class="bg-light px-2 py-1 rounded-pill">
                                    @for($s=1;$s<=5;$s++)
                                        <i class="bi bi-star{{ $s <= $review->rating ? '-fill' : '' }} text-warning" style="font-size: 10px;"></i>
                                    @endfor
                                </div>
                            </div>
                            
                            <p class="card-text text-dark" style="font-size: 14.5px; line-height: 1.7;">
                                {{ $review->comment }}
                            </p>
                            
                            @if($review->host_reply)
                            <div class="bg-light rounded-3 p-3 mt-3 border-start border-4 border-dark">
                                <div class="small fw-bold text-dark mb-2">
                                    <i class="bi bi-chat-dots-fill me-1"></i> پاسخ میزبان:
                                </div>
                                <p class="small text-muted mb-0" style="line-height: 1.6;">{{ $review->host_reply }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12 text-center py-5">
                    <div class="text-light mb-3" style="font-size: 4rem;">
                        <i class="bi bi-chat-square-text"></i>
                    </div>
                    <div class="text-secondary">هنوز نظری برای این اقامتگاه ثبت نشده است.</div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Map --}}
        @if($accommodation->lat && $accommodation->lng)
        <div class="py-5 border-top" id="sec-map">
            <h2 style="font-size:18px;font-weight:700;color:var(--bnb-dark);margin-bottom:8px;">موقعیت مکانی</h2>
            <p style="font-size:14px;color:var(--bnb-gray);margin-bottom:16px;">{{ $accommodation->city->province->name ?? '' }}، {{ $accommodation->city->name ?? '' }}@if($accommodation->address) — {{ $accommodation->address }}@endif</p>
            <div id="detailMap" style="height:300px;border-radius:12px;"></div>
        </div>
        @endif

    </div>{{-- /left --}}

    {{-- RIGHT: Booking Widget removed – using bottom pay bar instead --}}
    <div style="display:none;"
        x-data="bnbBookWidget('{{ request('check_in') }}','{{ request('check_out') }}',{{ request('guests',1) }})"
        @nav-search-updated.window="syncFromNav($event.detail)">
        <div class="bnb-book-widget" id="sec-book">
            <div class="bnb-book-price">
                <span class="text-xs text-slate-500 font-normal">شروع از</span>
                @auth
                    @if(Auth::user()->discount_percentage > 0)
                        @php $discShow = round($accommodation->lowest_price * (1 - Auth::user()->discount_percentage / 100)); @endphp
                        <span style="font-size:14px;text-decoration:line-through;color:var(--bnb-gray);font-weight:400;">{{ number_format($accommodation->lowest_price) }}</span>
                        <span class="font-bold text-xl" style="color:var(--bnb-red);">{{ number_format($discShow) }}</span>
                    @else
                        <span class="font-bold text-xl">{{ number_format($accommodation->lowest_price) }}</span>
                    @endif
                @else
                    <span class="font-bold text-xl">{{ number_format($accommodation->lowest_price) }}</span>
                @endauth
                <span class="text-xs">تومان</span>
                <span class="text-slate-400 font-normal">/ شب</span>
                @if($accommodation->averageRating() > 0)
                <div style="font-size:13px;color:var(--bnb-gray);font-weight:400;margin-top:4px;">
                    <i class="bi bi-star-fill" style="color:var(--bnb-dark);font-size:11px;"></i>
                    {{ $accommodation->averageRating() }} · {{ $accommodation->reviewCount() }} نظر
                </div>
                @endif
            </div>
            @auth
                @if(Auth::user()->discount_percentage > 0)
                <div class="bnb-alert bnb-alert-success" style="margin-bottom:16px;"><i class="bi bi-tag-fill me-1"></i>تخفیف <strong>{{ Auth::user()->discount_percentage }}٪</strong> برای شما</div>
                @endif
            @endauth
            @if($errors->any())
            <div class="bnb-alert bnb-alert-danger" style="margin-bottom:16px;">@foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach</div>
            @endif
            <form onsubmit="return false;" id="bookingForm">
                @csrf
                <div class="bnb-book-fields" :style="calOpen ? 'overflow:visible;' : ''">
                    <div class="bnb-book-field" style="cursor:pointer;position:relative;" @click.stop="openCal()" @click.outside="calOpen=false">
                        <label>تاریخ ورود و خروج</label>
                        <div class="picker-trigger" style="display:flex;align-items:center;justify-content:space-between;">
                            <span x-text="dateLabel || 'انتخاب تاریخ'" :style="dateLabel ? 'color:var(--bnb-dark);' : 'color:var(--bnb-gray);'"></span>
                            <i class="bi bi-calendar3" style="color:var(--bnb-gray);font-size:13px;"></i>
                        </div>
                        {{-- Inline calendar dropdown --}}
                        <div x-show="calOpen" x-cloak @click.stop
                             style="position:absolute;top:calc(100% + 8px);right:0;left:0;background:#fff;border:1px solid var(--bnb-border);border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.15);padding:16px;z-index:200;min-width:300px;">
                            {{-- Date summary row --}}
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                                <div :style="checkIn ? 'background:var(--bnb-red);color:#fff;' : 'background:var(--bnb-bg-light);color:var(--bnb-gray);'"
                                     style="flex:1;border-radius:10px;padding:7px 10px;text-align:center;transition:all .2s;">
                                    <div style="font-size:10px;font-weight:600;opacity:.8;margin-bottom:2px;">ورود</div>
                                    <div style="font-weight:600;font-size:13px;" x-text="checkIn ? jalaliStr(checkIn) : 'انتخاب'"></div>
                                </div>
                                <i class="bi bi-arrow-left" style="color:var(--bnb-gray);flex-shrink:0;"></i>
                                <div :style="checkOut ? 'background:var(--bnb-red);color:#fff;' : 'background:var(--bnb-bg-light);color:var(--bnb-gray);'"
                                     style="flex:1;border-radius:10px;padding:7px 10px;text-align:center;transition:all .2s;">
                                    <div style="font-size:10px;font-weight:600;opacity:.8;margin-bottom:2px;">خروج</div>
                                    <div style="font-weight:600;font-size:13px;" x-text="checkOut ? jalaliStr(checkOut) : 'انتخاب'"></div>
                                </div>
                            </div>
                            {{-- Month nav --}}
                            <div class="bnb-cal-header">
                                <button type="button" class="bnb-cal-nav" @click.stop="calNext()">&lsaquo;</button>
                                <span class="bnb-cal-title" x-text="calMonthLabel"></span>
                                <button type="button" class="bnb-cal-nav" @click.stop="calPrev()">&rsaquo;</button>
                            </div>
                            {{-- Day headers --}}
                            <div class="bnb-cal-dow">
                                <template x-for="h in ['ج','پ','چ','س','د','ی','ش']"><span x-text="h"></span></template>
                            </div>
                            {{-- Day grid --}}
                            <div class="bnb-cal-grid">
                                <template x-for="(cell, idx) in calDays" :key="idx">
                                    <button type="button"
                                        :disabled="!cell || cell.past"
                                        @click.stop="cell && !cell.past && selectCalDay(cell)"
                                        @mouseenter="cell && !cell.past && (calHover = cell.greg)"
                                        @mouseleave="calHover = null"
                                        :class="{
                                            'bnb-cal-cell':true,
                                            'cal-start':   cell && cell.greg === checkIn,
                                            'cal-end':     cell && cell.greg === checkOut,
                                            'cal-range':   calInRange(cell),
                                            'cal-hover-range': calHoverRange(cell),
                                            'cal-empty':   !cell
                                        }">
                                        <span x-text="cell ? cell.d : ''"></span>
                                    </button>
                                </template>
                            </div>
                            {{-- Footer --}}
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;">
                                <div>
                                    <span x-show="checkIn && checkOut" class="bnb-cal-nights" x-text="calNights + ' شب اقامت'"></span>
                                    <span x-show="checkIn && !checkOut" style="font-size:12px;color:var(--bnb-gray);">حالا تاریخ خروج را انتخاب کنید</span>
                                    <span x-show="!checkIn" style="font-size:12px;color:var(--bnb-gray);">تاریخ ورود را انتخاب کنید</span>
                                </div>
                                <div style="display:flex;gap:8px;">
                                    <button x-show="checkIn || checkOut" type="button"
                                        @click.stop="checkIn='';checkOut='';calPhase=0;updateLabel();"
                                        style="background:none;border:none;font-size:12px;color:var(--bnb-gray);text-decoration:underline;cursor:pointer;font-family:var(--bnb-font);">پاک</button>
                                    <button x-show="checkIn && checkOut" type="button"
                                        @click.stop="calOpen=false"
                                        class="btn-bnb" style="padding:6px 14px;font-size:12px;">تأیید</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bnb-book-field">
                        <label>تعداد مهمان</label>
                        <div class="d-flex align-items-center justify-content-between" style="padding-top:2px;">
                            <button type="button"
                                @click.stop="if(guests > 1) guests--"
                                style="width:32px;height:32px;border-radius:50% !important;border:1.5px solid var(--bnb-border);background:#fff;font-size:16px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--bnb-dark);padding:0 !important;"
                                :style="{ opacity: guests <= 1 ? 0.35 : 1, cursor: guests <= 1 ? 'not-allowed' : 'pointer' }"
                                :disabled="guests <= 1">−</button>
                            <span x-text="guests + ' مهمان'" style="font-size:14px;font-weight:600;color:var(--bnb-dark);"></span>
                            <button type="button"
                                @click.stop="if(guests < 20) guests++"
                                style="width:32px;height:32px;border-radius:50% !important;border:1.5px solid var(--bnb-border);background:#fff;font-size:16px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--bnb-dark);padding:0 !important;"
                                :style="{ opacity: guests >= 20 ? 0.35 : 1, cursor: guests >= 20 ? 'not-allowed' : 'pointer' }"
                                :disabled="guests >= 20">+</button>
                        </div>
                    </div>
                </div>

                @auth
                    @if($roomTypes->isNotEmpty())
                    <a href="#sec-rooms" class="btn-bnb d-block text-decoration-none text-center mt-3" style="background:linear-gradient(to left, #E31C5F, var(--bnb-red));">مشاهده اتاق‌ها و رزرو</a>
                    @else
                    <button type="button" @click="submitBooking()" class="btn-bnb w-100 mt-3" style="background:linear-gradient(to left, #E31C5F, var(--bnb-red));">ثبت رزرو</button>
                    @endif
                @else
                <a href="{{ route('auth.mobile') }}" class="btn-bnb d-block text-decoration-none text-center mt-3" style="background:linear-gradient(to left, #E31C5F, var(--bnb-red));"><i class="bi bi-phone me-1"></i>ورود برای رزرو</a>
                @endauth
            </form>
            <div class="bnb-price-breakdown" :class="checkIn && checkOut ? '' : 'd-none'">
                <div class="bnb-price-row">
                    <span>{{ number_format($accommodation->price_per_night) }} × <span x-text="calNights">0</span> شب</span>
                    <span x-text="(calNights * {{ $accommodation->price_per_night }}).toLocaleString('fa-IR') + ' تومان'">-</span>
                </div>
                @auth
                    @if(Auth::user()->discount_percentage > 0)
                    <div class="bnb-price-row" style="color:var(--bnb-red);">
                        <span>تخفیف {{ Auth::user()->discount_percentage }}٪</span>
                        <span x-text="'-' + Math.round((calNights * {{ $accommodation->price_per_night }} * {{ Auth::user()->discount_percentage }} / 100)).toLocaleString('fa-IR') + ' تومان'">-</span>
                    </div>
                    @endif
                @endauth
                <div class="bnb-price-row total">
                    <span>جمع کل</span>
                    <span x-text="Math.round((calNights * {{ $accommodation->price_per_night }} * (1 - {{ auth()->check() ? auth()->user()->discount_percentage : 0 }} / 100))).toLocaleString('fa-IR') + ' تومان'">-</span>
                </div>
            </div>
            <p style="font-size:12px;color:var(--bnb-gray);text-align:center;margin-top:12px;"><i class="bi bi-shield-check me-1"></i>تا زمان تأیید نهایی، هیچ مبلغی دریافت نمی‌شود</p>
        </div>
    </div>

</div>{{-- /layout --}}
</div>{{-- /container --}}

{{-- ═══════════════════════════════════════════════════
     BOOKING BAR  (fixed bottom – all screens)
═══════════════════════════════════════════════════ --}}
{{-- Quick-Book Drawer --}}
<div x-data="mbbDrawer()" @keydown.escape.window="drawerOpen=false">

    {{-- Backdrop --}}
    <div x-show="drawerOpen" x-transition.opacity
         style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1060;"
         @click="drawerOpen=false" x-cloak></div>

    {{-- Slide-up sheet --}}
    <div x-show="drawerOpen" x-cloak
         x-transition
         style="position:fixed;bottom:0;right:0;left:0;z-index:1071;background:#fff;border-radius:20px 20px 0 0;padding:20px 16px 32px;max-height:90vh;overflow-y:auto;max-width:500px;margin:0 auto;box-shadow: 0 -8px 30px rgba(0,0,0,0.15);">

        {{-- Handle + title --}}
        <div style="text-align:center;margin-bottom:16px;">
            <div style="width:36px;height:4px;border-radius:2px;background:var(--bnb-border);margin:0 auto 14px;"></div>
            <div style="font-size:16px;font-weight:700;color:var(--bnb-dark);" x-text="roomTypeName || 'تاریخ و تعداد نفرات را انتخاب کنید'"></div>
            <div x-show="roomTypeCapacity" style="font-size:12px;color:var(--bnb-gray);margin-top:4px;">
                <i class="bi bi-people me-1"></i><span x-text="'ظرفیت: ' + roomTypeCapacity + ' نفر'"></span>
            </div>
        </div>

        {{-- Date summary badges --}}
        <div style="display:flex;gap:8px;margin-bottom:16px;">
            <div :style="checkIn ? 'border-color:var(--bnb-red);background:rgba(255,56,92,.06);' : ''"
                 style="flex:1;border:1.5px solid var(--bnb-border);border-radius:10px;padding:9px 12px;text-align:center;">
                <div style="font-size:10px;font-weight:700;color:var(--bnb-gray);margin-bottom:3px;">ورود</div>
                <div style="font-size:13px;font-weight:600;" x-text="checkIn ? jalStr(checkIn) : 'انتخاب'"></div>
            </div>
            <div :style="checkOut ? 'border-color:var(--bnb-red);background:rgba(255,56,92,.06);' : ''"
                 style="flex:1;border:1.5px solid var(--bnb-border);border-radius:10px;padding:9px 12px;text-align:center;">
                <div style="font-size:10px;font-weight:700;color:var(--bnb-gray);margin-bottom:3px;">خروج</div>
                <div style="font-size:13px;font-weight:600;" x-text="checkOut ? jalStr(checkOut) : 'انتخاب'"></div>
            </div>
        </div>

        {{-- Inline Jalali Calendar --}}
        <div style="margin-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <button type="button" @click.stop="calNext()" style="background:none;border:1px solid var(--bnb-border);border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;">&lsaquo;</button>
                <span style="font-size:14px;font-weight:700;" x-text="calMonthLabel"></span>
                <button type="button" @click.stop="calPrev()" style="background:none;border:1px solid var(--bnb-border);border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;">&rsaquo;</button>
            </div>

            {{-- Legend (only for room-type bookings) --}}
            <div x-show="roomTypeId" class="bnb-avail-legend">
                <div class="bnb-avail-legend-item"><div class="bnb-legend-dot" style="background:#10b981;"></div>موجود</div>
                <div class="bnb-avail-legend-item"><div class="bnb-legend-dot" style="background:#f59e0b;"></div>ظرفیت محدود</div>
                <div class="bnb-avail-legend-item"><div class="bnb-legend-dot" style="background:#e5e7eb;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(0,0,0,0.1) 3px,rgba(0,0,0,0.1) 4px);"></div>تکمیل</div>
                <div class="bnb-avail-legend-item"><div class="bnb-legend-dot" style="background:#fff0f0;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(220,38,38,0.15) 3px,rgba(220,38,38,0.15) 4px);border:1px solid #fca5a5;"></div>مسدود</div>
            </div>

            {{-- Loading state --}}
            <div x-show="availabilityLoading" class="bnb-avail-loading">
                <div class="bnb-spinner"></div>
                <span>در حال بررسی ظرفیت...</span>
            </div>

            {{-- Error state --}}
            <div x-show="availabilityError && !availabilityLoading" style="font-size:12px;color:#e53e3e;text-align:center;padding:8px;margin-bottom:8px;">
                <i class="bi bi-exclamation-triangle me-1"></i>خطا در بارگذاری ظرفیت. تقویم به‌صورت عادی نمایش داده می‌شود.
            </div>

            <div style="display:grid;grid-template-columns:repeat(7,1fr);text-align:center;margin-bottom:4px;">
                <template x-for="h in ['ج','پ','چ','س','د','ی','ش']">
                    <span style="font-size:11px;color:var(--bnb-gray);padding:4px 0;" x-text="h"></span>
                </template>
            </div>
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;">
                <template x-for="(cell, idx) in calDays" :key="idx">
                    <button type="button"
                        :disabled="!cell || cell.past || cell.isUnavailable || cell.isBlocked || cell.disabledByGap"
                        @click.stop="cell && !cell.past && !cell.isUnavailable && !cell.isBlocked && !cell.disabledByGap && selectDay(cell)"
                        :title="cell && cell.availInfo ? cell.availInfo : ''"
                        :class="{
                            'bnb-cal-cell':    true,
                            'cal-start':       cell && cell.greg === checkIn,
                            'cal-end':         cell && cell.greg === checkOut,
                            'cal-range':       cell && checkIn && checkOut && cell.greg > checkIn && cell.greg < checkOut,
                            'cal-empty':       !cell,
                            'cal-unavailable': cell && !cell.past && cell.isUnavailable,
                            'cal-blocked':     cell && !cell.past && cell.isBlocked,
                            'cal-low-avail':   cell && !cell.past && !cell.isUnavailable && !cell.isBlocked && cell.isLowAvail,
                            'cal-avail':       cell && !cell.past && !cell.isUnavailable && !cell.isBlocked && !cell.isLowAvail && cell.hasAvailData
                        }">
                        <span x-text="cell ? cell.d : ''"></span>
                        <template x-if="cell && !cell.past && !cell.isUnavailable && !cell.isBlocked && cell.isLowAvail">
                            <div class="avail-dot"></div>
                        </template>
                        <template x-if="cell && !cell.past && !cell.isUnavailable && !cell.isBlocked && !cell.isLowAvail && cell.hasAvailData">
                            <div class="avail-dot"></div>
                        </template>
                    </button>
                </template>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                <div>
                    <span x-show="checkIn && checkOut" style="font-size:12px;color:var(--bnb-gray);" x-text="nights + ' شب اقامت'"></span>
                    <span x-show="checkIn && !checkOut" style="font-size:12px;color:var(--bnb-gray);">تاریخ خروج را انتخاب کنید</span>
                    <span x-show="!checkIn" style="font-size:12px;color:var(--bnb-gray);">تاریخ ورود را انتخاب کنید</span>
                </div>
                <button x-show="checkIn" type="button" @click.stop="checkIn='';checkOut='';calPhase=0;"
                    style="background:none;border:none;font-size:12px;color:var(--bnb-gray);text-decoration:underline;cursor:pointer;font-family:var(--bnb-font);">پاک کردن</button>
            </div>
        </div>

        {{-- Guests counter --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-top:1px solid var(--bnb-border);">
            <div>
                <div style="font-size:14px;font-weight:600;">تعداد نفرات</div>
                <div style="font-size:12px;color:var(--bnb-gray);">بزرگسال و کودک</div>
            </div>
            <div style="display:flex;align-items:center;gap:16px;">
                <button type="button" class="bnb-cnt-btn" @click.stop="guests>1 && guests--" :disabled="guests<=1"><i class="bi bi-dash"></i></button>
                <span x-text="guests" style="min-width:24px;text-align:center;font-size:16px;font-weight:600;"></span>
                <button type="button" class="bnb-cnt-btn" @click.stop="guests<16 && guests++"><i class="bi bi-plus"></i></button>
            </div>
        </div>

        {{-- Confirm dates button --}}
        <button type="button" @click="confirmDates()"
                :disabled="!checkIn || !checkOut"
                :style="(!checkIn || !checkOut) ? 'opacity:.5;cursor:not-allowed;' : ''"
                class="btn-bnb"
                style="width:100%;padding:14px;font-size:15px;border-radius:12px;margin-top:16px;">
            <i class="bi bi-check-circle me-1"></i> تأیید
        </button>
    </div>

    {{-- Sticky bottom pay bar --}}
    <div x-show="datesConfirmed && checkIn && checkOut && targetForm" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="bnb-pay-bar">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;max-width:600px;margin:0 auto;">
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;">
                    <template x-if="originalPrice > 0 && originalPrice !== pricePerNight">
                        <span style="font-size:12px;text-decoration:line-through;color:var(--bnb-gray);font-weight:400;" x-text="(nights * originalPrice).toLocaleString('fa-IR') + ' تومان'"></span>
                    </template>
                    <span style="font-size:16px;font-weight:700;color:var(--bnb-dark);" x-text="(nights * pricePerNight).toLocaleString('fa-IR') + ' تومان'"></span>
                </div>
                <div style="font-size:12px;color:var(--bnb-gray);margin-top:2px;" x-text="nights + ' شب · ' + guests + ' نفر'"></div>
            </div>
            <button type="button" @click="pay()"
                    class="btn-bnb"
                    style="padding:12px 28px;border-radius:12px;font-size:14px;white-space:nowrap;flex-shrink:0;">
                <i class="bi bi-credit-card me-1"></i> پرداخت
            </button>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script src="{{ asset('vendor/swiper/swiper-bundle.min.js') }}"></script>
<script type="module">
import PhotoSwipeLightbox from '{{ asset("vendor/photoswipe/photoswipe-lightbox.esm.min.js") }}';
const lighthouse = new PhotoSwipeLightbox({
  gallery: '#zoom-gallery',
  children: '.pswp-gallery-item',
  pswpModule: () => import('{{ asset("vendor/photoswipe/photoswipe.esm.min.js") }}'),
  clickToCloseNonZoomable: true,
  padding: { top: 20, bottom: 20, left: 20, right: 20 }
});
lighthouse.on('pointerDown', (e) => {
    if (e.originalEvent.target.classList.contains('pswp__img')) return;
    lighthouse.pswp.close();
});
lighthouse.init();

const pswpBtnAll = document.getElementById('pswp-btn-all');
if (pswpBtnAll) {
    pswpBtnAll.addEventListener('click', () => {
        lighthouse.loadAndOpen(0);
    });
}

// PhotoSwipe for Mobile Slider
const mobileLighthouse = new PhotoSwipeLightbox({
  gallery: '#mobile-zoom-gallery',
  children: '.pswp-gallery-item-mobile',
  pswpModule: () => import('{{ asset("vendor/photoswipe/photoswipe.esm.min.js") }}'),
});
// Prevent default link behavior for fast clicks/taps
mobileLighthouse.on('pointerDown', (e) => {
    e.originalEvent.preventDefault();
});
mobileLighthouse.init();

// Initialize Swiper for mobile slider
new Swiper('.bnb-mobile-slider', {
    pagination: {
        el: '.swiper-pagination',
        type: 'bullets',
        clickable: true
    },
    loop: true,
});
</script>
<script>
function reserveRoom(btn, pricePerNight, origPrice, roomTypeId) {
    var form  = btn.closest('form');
    var price = pricePerNight || 0;
    var orig  = origPrice || price;
    var rtId  = roomTypeId || null;

    // Read room-type meta from the card
    var card      = btn.closest('.bnb-room-card');
    var rtName    = card ? (card.querySelector('[data-rt-name]')?.dataset.rtName || '') : '';
    var rtCap     = card ? (card.querySelector('[data-rt-cap]')?.dataset.rtCap || '') : '';

    var drawerEl = document.querySelector('[x-data="mbbDrawer()"]');
    if (drawerEl && typeof Alpine !== 'undefined') {
        var drawer = Alpine.$data(drawerEl);
        drawer.openForRoom(form, price, orig, rtId, rtName, rtCap);
    }
}

// Price breakdown is handled reactively by Alpine.js in the booking widget.

function bnbBookWidget(initCheckIn, initCheckOut, initGuests) {
    return {
        checkIn:  initCheckIn  || '',
        checkOut: initCheckOut || '',
        guests:   parseInt(initGuests) || 1,
        dateLabel: '',
        // Calendar state
        calOpen:  false,
        calYear:  null,
        calMonth: null,
        calPhase: 0,
        calHover: null,

        get calNights() {
            if (!this.checkIn || !this.checkOut) return 0;
            return Math.round((new Date(this.checkOut) - new Date(this.checkIn)) / 86400000);
        },

        get calMonthLabel() {
            if (!this.calYear) return '';
            const n = ['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
            return n[this.calMonth] + ' ' + this.calYear;
        },

        get calDays() {
            if (!this.calYear || typeof persianDate === 'undefined') return [];
            const pd   = new persianDate([this.calYear, this.calMonth, 1]);
            const fdow = pd.day();
            const dim  = pd.daysInMonth();
            const now  = new persianDate();
            const ty = now.year(), tm = now.month(), td = now.date();
            const offset = (6 - fdow + 7) % 7;
            let cells = [];
            for (let i = 0; i < offset; i++) cells.push(null);
            for (let d = 1; d <= dim; d++) {
                const dt = new persianDate([this.calYear, this.calMonth, d]).toDate();
                const greg = dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0');
                const past = (this.calYear < ty) || (this.calYear === ty && this.calMonth < tm) || (this.calYear === ty && this.calMonth === tm && d < td);
                cells.push({ d, greg, past });
            }
            return cells;
        },

        init() {
            if (typeof persianDate !== 'undefined') {
                const t = new persianDate();
                this.calYear  = t.year();
                this.calMonth = t.month();
            }
            this.updateLabel();
            // Sync widget changes → Navbar
            this.$watch('checkIn',  () => this._syncToNav());
            this.$watch('checkOut', () => this._syncToNav());
            this.$watch('guests',   () => this._syncToNav());
        },

        _syncToNav() {
            const navEl = document.querySelector('[x-data="bnbNavSearch()"]');
            if (!navEl || typeof Alpine === 'undefined') return;
            const nav = Alpine.$data(navEl);
            if (nav.checkIn  !== this.checkIn)  nav.checkIn  = this.checkIn;
            if (nav.checkOut !== this.checkOut) nav.checkOut = this.checkOut;
            if (nav.guests   !== this.guests)   nav.guests   = this.guests;
        },

        jalaliStr(g) {
            if (!g) return '';
            try { return new persianDate(new Date(g + 'T12:00:00')).format('YYYY/MM/DD'); } catch(e) { return g; }
        },

        updateLabel() {
            if (this.checkIn && this.checkOut) {
                this.dateLabel = this.jalaliStr(this.checkIn) + ' — ' + this.jalaliStr(this.checkOut);
            } else if (this.checkIn) {
                this.dateLabel = 'ورود: ' + this.jalaliStr(this.checkIn);
            } else {
                this.dateLabel = '';
            }
        },

        openCal() {
            this.calOpen = !this.calOpen;
        },

        calPrev() {
            if (this.calMonth === 1) { this.calYear--; this.calMonth = 12; }
            else this.calMonth--;
        },

        calNext() {
            if (this.calMonth === 12) { this.calYear++; this.calMonth = 1; }
            else this.calMonth++;
        },

        selectCalDay(cell) {
            if (!cell || cell.past) return;
            if (this.calPhase === 0) {
                this.checkIn  = cell.greg;
                this.checkOut = '';
                this.calPhase = 1;
                this.updateLabel();
            } else {
                if (cell.greg > this.checkIn) {
                    this.checkOut = cell.greg;
                    this.calPhase = 0;
                    this.calHover = null;
                    this.updateLabel();
                    this.calOpen  = false;
                } else {
                    this.checkIn  = cell.greg;
                    this.checkOut = '';
                    this.updateLabel();
                }
            }
        },

        calInRange(cell) {
            if (!cell || !this.checkIn || !this.checkOut) return false;
            return cell.greg > this.checkIn && cell.greg < this.checkOut;
        },

        calHoverRange(cell) {
            if (!cell || this.calPhase !== 1 || !this.checkIn || !this.calHover) return false;
            if (this.calHover > this.checkIn)
                return cell.greg > this.checkIn && cell.greg < this.calHover;
            return false;
        },

        syncFromNav(detail) {
            const ci = detail.checkIn  || '';
            const co = detail.checkOut || '';
            const g  = detail.guests   || 1;
            // Guard: only update if actually changed (prevents infinite loop with _syncToNav)
            if (ci === this.checkIn && co === this.checkOut && g === this.guests) return;
            this.checkIn  = ci;
            this.checkOut = co;
            this.guests   = g;
            this.updateLabel();
        },

        submitBooking() {
            if (!this.checkIn || !this.checkOut) {
                this.calOpen = true;
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('bookings.store', $accommodation) }}';
            const addInput = (name, val) => { const i = document.createElement('input'); i.type='hidden'; i.name=name; i.value=val; form.appendChild(i); };
            addInput('_token', '{{ csrf_token() }}');
            addInput('check_in',  this.checkIn);
            addInput('check_out', this.checkOut);
            addInput('guests',    this.guests);
            document.body.appendChild(form);
            form.submit();
        }
    };
}

function mbbDrawer() {
    return {
        drawerOpen: false,
        datesConfirmed: false,
        checkIn: '',
        checkOut: '',
        guests: 1,
        calYear: null,
        calMonth: null,
        calPhase: 0,
        targetForm: null,
        pricePerNight: 0,
        originalPrice: 0,
        // Availability
        roomTypeId: null,
        roomTypeName: '',
        roomTypeCapacity: '',
        availabilityData: {},
        availabilityLoading: false,
        availabilityError: false,
        loadedMonths: [],

        get nights() {
            if (!this.checkIn || !this.checkOut) return 0;
            return Math.round((new Date(this.checkOut) - new Date(this.checkIn)) / 86400000);
        },

        get calMonthLabel() {
            if (!this.calYear) return '';
            const n = ['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
            return n[this.calMonth] + ' ' + this.calYear;
        },

        // Returns the first blocker (blocked/full) date that is STRICTLY AFTER `from`
        _firstBlockerAfter(from) {
            const dates = Object.keys(this.availabilityData).sort();
            for (const d of dates) {
                if (d > from) {
                    const a = this.availabilityData[d];
                    if (a && (a.is_blocked || a.available_rooms <= 0)) return d;
                }
            }
            return null;
        },

        get calDays() {
            if (!this.calYear || typeof persianDate === 'undefined') return [];
            const pd   = new persianDate([this.calYear, this.calMonth, 1]);
            const fdow = pd.day();
            const dim  = pd.daysInMonth();
            const now  = new persianDate();
            const ty = now.year(), tm = now.month(), td = now.date();
            const offset = (6 - fdow + 7) % 7;

            // First blocker after checkIn (for range constraint in phase 1)
            const firstBlocker = (this.calPhase === 1 && this.checkIn)
                ? this._firstBlockerAfter(this.checkIn)
                : null;

            let cells = [];
            for (let i = 0; i < offset; i++) cells.push(null);
            for (let d = 1; d <= dim; d++) {
                const dt = new persianDate([this.calYear, this.calMonth, d]).toDate();
                const greg = dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0');
                const past = (this.calYear < ty) || (this.calYear === ty && this.calMonth < tm) || (this.calYear === ty && this.calMonth === tm && d < td);

                const avail        = this.availabilityData[greg];
                const hasAvailData = !!avail;
                const isBlocked    = avail ? avail.is_blocked : false;
                const isUnavailable= avail ? (!avail.is_blocked && avail.available_rooms <= 0) : false;
                const isLowAvail   = avail ? (!avail.is_blocked && avail.available_rooms > 0 && avail.available_rooms < avail.total) : false;

                // In phase 1 (picking checkout): dates after the first blocker are also disabled
                // But: check_out = firstBlocker itself is ALLOWED (guest leaves that day, doesn't stay that night)
                const disabledByGap = this.calPhase === 1 && firstBlocker !== null && greg > firstBlocker;

                let availInfo = '';
                if (avail && !past) {
                    if (avail.is_blocked) availInfo = 'مسدود شده توسط میزبان';
                    else if (avail.available_rooms <= 0) availInfo = 'تمام شد';
                    else if (avail.available_rooms === 1) availInfo = '۱ اتاق باقیمانده';
                    else availInfo = avail.available_rooms + ' اتاق موجود';
                }

                cells.push({ d, greg, past, isBlocked, isUnavailable, isLowAvail, hasAvailData, disabledByGap, availInfo });
            }
            return cells;
        },

        init() {
            if (typeof persianDate !== 'undefined') {
                const t = new persianDate();
                this.calYear  = t.year();
                this.calMonth = t.month();
            }
            this.$watch('drawerOpen', val => {
                const nav = document.querySelector('.bnb-bottom-nav');
                if (nav) nav.style.display = val ? 'none' : '';

                if (val && this.checkIn) {
                    try {
                        const pDate = new persianDate(new Date(this.checkIn + 'T12:00:00'));
                        this.calYear = pDate.year();
                        this.calMonth = pDate.month();
                    } catch(e) {}
                }
            });
            this.$watch('checkIn',  () => { if (this.drawerOpen) this.datesConfirmed = false; });
            this.$watch('checkOut', () => { if (this.drawerOpen) this.datesConfirmed = false; });
            // Pre-fill from booking widget if available
            const widgetEl = document.querySelector('[x-data^="bnbBookWidget"]');
            if (widgetEl && typeof Alpine !== 'undefined') {
                this.$nextTick(() => {
                    try {
                        const w = Alpine.$data(widgetEl);
                        if (w.checkIn)  this.checkIn  = w.checkIn;
                        if (w.checkOut) this.checkOut = w.checkOut;
                        if (w.guests)   this.guests   = w.guests;
                    } catch(e) {}
                });
            }
            window.addEventListener('nav-search-updated', (e) => {
                if (e.detail.checkIn)  this.checkIn  = e.detail.checkIn;
                if (e.detail.checkOut) this.checkOut = e.detail.checkOut;
                if (e.detail.guests)   this.guests   = e.detail.guests;
            });
        },

        // Convert Jalali year+month (with optional offset) to Gregorian YYYY-MM for API calls
        _gregYmForJalali(jYear, jMonth) {
            while (jMonth > 12) { jMonth -= 12; jYear++; }
            while (jMonth < 1)  { jMonth += 12; jYear--; }
            const gd = new persianDate([jYear, jMonth, 1]).toDate();
            return gd.getFullYear() + '-' + String(gd.getMonth() + 1).padStart(2, '0');
        },

        async fetchAvailability(months) {
            if (!this.roomTypeId) return;
            const toFetch = months.filter(m => !this.loadedMonths.includes(m));
            if (!toFetch.length) return;

            this.availabilityLoading = true;
            this.availabilityError   = false;
            try {
                const params = new URLSearchParams({ months: toFetch.join(',') });
                const resp = await fetch('/api/room-types/' + this.roomTypeId + '/availability?' + params, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();
                Object.assign(this.availabilityData, data.dates || {});
                this.loadedMonths.push(...toFetch);
            } catch(e) {
                this.availabilityError = true;
            } finally {
                this.availabilityLoading = false;
            }
        },

        _ensureMonthLoaded() {
            if (!this.roomTypeId) return;
            const curr = this._gregYmForJalali(this.calYear, this.calMonth);
            const next = this._gregYmForJalali(this.calYear, this.calMonth + 1);
            this.fetchAvailability([curr, next]);
        },

        jalStr(g) {
            if (!g) return '';
            try { return new persianDate(new Date(g + 'T12:00:00')).format('YYYY/MM/DD'); } catch(e) { return g; }
        },

        calPrev() {
            if (this.calMonth === 1) { this.calYear--; this.calMonth = 12; }
            else this.calMonth--;
            this._ensureMonthLoaded();
        },

        calNext() {
            if (this.calMonth === 12) { this.calYear++; this.calMonth = 1; }
            else this.calMonth++;
            this._ensureMonthLoaded();
        },

        selectDay(cell) {
            if (!cell || cell.past || cell.isUnavailable || cell.isBlocked || cell.disabledByGap) return;
            if (this.calPhase === 0) {
                this.checkIn  = cell.greg;
                this.checkOut = '';
                this.calPhase = 1;
            } else {
                if (cell.greg > this.checkIn) {
                    this.checkOut = cell.greg;
                    this.calPhase = 0;
                } else {
                    // Clicked before or equal to checkIn → restart
                    this.checkIn  = cell.greg;
                    this.checkOut = '';
                }
            }
        },

        onBookClick() {
            if (this.checkIn && this.checkOut) {
                this._scrollToRooms();
            } else {
                this.drawerOpen = true;
            }
        },

        confirmDates() {
            if (!this.checkIn || !this.checkOut) return;
            this.datesConfirmed = true;
            this.drawerOpen = false;
        },

        pay() {
            if (!this.checkIn || !this.checkOut || !this.targetForm) return;
            const form = this.targetForm;
            const ci = form.querySelector('.rt-check-in');
            const co = form.querySelector('.rt-check-out');
            const g  = form.querySelector('input[name="guests"]');
            if (ci) ci.value = this.checkIn;
            if (co) co.value = this.checkOut;
            if (g)  g.value  = this.guests;
            form.submit();
        },

        openForRoom(form, price, origPrice, roomTypeId, rtName, rtCap) {
            this.targetForm       = form;
            this.pricePerNight    = price;
            this.originalPrice    = origPrice || price;
            this.roomTypeId       = roomTypeId || null;
            this.roomTypeName     = rtName || '';
            this.roomTypeCapacity = rtCap || '';
            this.datesConfirmed   = false;
            this.drawerOpen       = true;

            if (roomTypeId) {
                // Load current + next 2 Gregorian months (converted from Jalali calendar state)
                const months = [0, 1, 2].map(i => this._gregYmForJalali(this.calYear, this.calMonth + i));
                this.fetchAvailability(months);
            }
        },

        _scrollToRooms() {
            const el = document.getElementById('sec-rooms');
            if (!el) return;
            const nav = document.getElementById('bnbNavbar');
            const navH = (nav && nav.classList.contains('is-mini')) ? 64 : 80;
            const top = el.getBoundingClientRect().top + window.pageYOffset - navH - 50;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    };
}

function highlightStars(count) {
    document.querySelectorAll('#starSelector .bi').forEach(function(el, i) { el.className = 'bi bi-star' + (i < count ? '-fill' : ''); });
}
var starSelector = document.getElementById('starSelector');
if (starSelector) {
    starSelector.querySelectorAll('.bi').forEach(function(el) {
        el.addEventListener('click', function() { var v = parseInt(this.dataset.val); document.getElementById('ratingInput').value = v; highlightStars(v); });
        el.addEventListener('mouseenter', function() { highlightStars(parseInt(this.dataset.val)); });
    });
    starSelector.addEventListener('mouseleave', function() { highlightStars(parseInt(document.getElementById('ratingInput').value) || 5); });
    highlightStars(parseInt(document.getElementById('ratingInput')?.value) || 5);
}

@if($accommodation->lat && $accommodation->lng)
document.addEventListener('DOMContentLoaded', function() {
    var detailMap = L.map('detailMap').setView([{{ $accommodation->lat }}, {{ $accommodation->lng }}], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution:'© OpenStreetMap'}).addTo(detailMap);
    L.marker([{{ $accommodation->lat }}, {{ $accommodation->lng }}]).bindPopup('<strong>{{ $accommodation->name }}</strong>').addTo(detailMap).openPopup();
});
@endif

window.addEventListener('scroll', function() {
    var sections = ['sec-info','sec-amenities','sec-rooms','sec-reviews','sec-map'];
    var tabs = document.querySelectorAll('.bnb-sticky-tab');
    var nav = document.getElementById('bnbNavbar');
    // We use a more robust check for height
    var navHeight = (nav && nav.classList.contains('is-mini')) ? 64 : 80;
    var tabsHeight = 50; // Approximated height of .bnb-sticky-tabs
    var offset = navHeight + tabsHeight + 10;
    
    var active = 'sec-info';
    sections.forEach(function(id) { 
        var el = document.getElementById(id); 
        if (el && window.scrollY >= el.offsetTop - offset) active = id; 
    });
    tabs.forEach(function(tab) { 
        tab.classList.remove('active'); 
        if (tab.getAttribute('href') === '#' + active) tab.classList.add('active'); 
    });
});

// Smooth scroll with correct offset for anchors
document.querySelectorAll('.bnb-sticky-tab, .bnb-location-link, .bnb-rating-count').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href') || this.getAttribute('onclick')?.match(/#[\w-]+/)?.[0];
        if (!href || !href.startsWith('#')) return;
        
        const target = document.querySelector(href);
        if (target) {
            e.preventDefault();
            const nav = document.getElementById('bnbNavbar');
            const navHeight = (nav && nav.classList.contains('is-mini')) ? 64 : 80;
            const tabsHeight = 48; 
            const offset = navHeight + tabsHeight - 2; 
            
            const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - offset;
            window.scrollTo({ top: targetPosition, behavior: 'smooth' });
        }
    });
});

@auth
var _userFavorites = @json(Auth::user()->favorites()->pluck('accommodations.id')->toArray());
@else
var _userFavorites = [];
@endauth

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
        var icon = btn.querySelector('i');
        if (data.favorited) {
            icon.classList.replace('bi-heart','bi-heart-fill');
            btn.style.color = 'var(--bnb-red)';
            _userFavorites.push(id);
        } else {
            icon.classList.replace('bi-heart-fill','bi-heart');
            btn.style.color = '';
            _userFavorites = _userFavorites.filter(function(x){ return x !== id; });
        }
    });
}

(function () {
    var showBtn = document.querySelector('.bnb-filter-pill[onclick*="toggleWishlist"]');
    if (showBtn) {
        var accId = {{ $accommodation->id }};
        var icon = showBtn.querySelector('i');
        if (icon && _userFavorites.includes(accId)) {
            icon.classList.replace('bi-heart','bi-heart-fill');
            showBtn.style.color = 'var(--bnb-red)';
        }
    }
})();
</script>
@endpush
