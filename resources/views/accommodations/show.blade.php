@extends('layouts.app')

@section('title', $accommodation->name)

@push('styles')
<style>
/* ── Photo grid ─────────────────────────────────────── */
.bnb-photo-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 220px 220px;
    gap: 8px;
    border-radius: var(--bnb-radius);
    overflow: hidden;
    margin: 0 -24px;
}
.bnb-photo-grid-main { grid-row: 1 / 3; }
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
    .bnb-photo-grid { grid-template-columns: 1fr; grid-template-rows: 260px; margin: 0 -16px; }
    .bnb-photo-grid-wrap:not(.bnb-photo-grid-main) { display: none; }
}
/* ── Page layout ─────────────────────────────────────── */
.bnb-show-layout { display: grid; grid-template-columns: 1fr 380px; gap: 60px; padding-top: 28px; }
.bnb-show-right { position: sticky; top: 80px; align-self: start; }
@media (max-width: 991px) {
    .bnb-show-layout { grid-template-columns: 1fr; gap: 0; }
    .bnb-show-right { position: static; margin-top: 32px; }
}
/* ── Booking widget ──────────────────────────────────── */
.bnb-book-widget { border: 1px solid var(--bnb-border); border-radius: 12px; padding: 24px; box-shadow: 0 6px 20px rgba(0,0,0,.1); }
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
.bnb-sticky-tabs { position: sticky; top: 60px; background: #fff; border-bottom: 1px solid var(--bnb-border); z-index: 90; margin: 0 -24px; padding: 0 24px; display: flex; gap: 0; overflow-x: auto; scrollbar-width: none; }
.bnb-sticky-tabs::-webkit-scrollbar { display: none; }
.bnb-sticky-tab { padding: 14px 16px; font-size: 14px; font-weight: 500; color: var(--bnb-gray); border-bottom: 2px solid transparent; cursor: pointer; white-space: nowrap; text-decoration: none; transition: color .15s, border-color .15s; }
.bnb-sticky-tab:hover, .bnb-sticky-tab.active { color: var(--bnb-dark); border-bottom-color: var(--bnb-dark); }
/* ── Amenities grid ──────────────────────────────────── */
.bnb-amenities-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }
.bnb-amenity-item { display: flex; align-items: center; gap: 12px; font-size: 14px; color: var(--bnb-dark); }
/* ── Room card ────────────────────────────────────────── */
.bnb-room-card { border: 1px solid var(--bnb-border); border-radius: 12px; overflow: hidden; margin-bottom: 16px; }
.bnb-rate-row { display: grid; grid-template-columns: 1fr auto auto; gap: 16px; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--bnb-border); }
.bnb-rate-row:last-child { border-bottom: none; }
/* ── Star selector ────────────────────────────────────── */
.bnb-star-selector { display: flex; gap: 4px; font-size: 24px; cursor: pointer; }
.bnb-star-selector .bi { color: var(--bnb-dark); transition: color .15s; }
/* ── Photo modal ──────────────────────────────────────── */
#photoModal .modal-dialog { max-width: 900px; }
#photoModal .photo-grid { display: flex; flex-direction: column; gap: 8px; }
#photoModal .photo-grid img { width: 100%; border-radius: 8px; object-fit: cover; }
</style>
@endpush

@section('content')
@php
    $allImages = collect($accommodation->images ?? [])->filter()->values();
    if ($accommodation->image && !$allImages->contains($accommodation->image)) {
        $allImages->prepend($accommodation->image);
    }
    $img0 = $allImages->get(0); $img1 = $allImages->get(1);
    $img2 = $allImages->get(2); $img3 = $allImages->get(3); $img4 = $allImages->get(4);
@endphp

<div class="container-fluid px-3 px-lg-5">

{{-- PHOTO GRID --}}
<div style="position:relative;padding-top:24px;">
    <div class="bnb-photo-grid">
        <div class="bnb-photo-grid-wrap bnb-photo-grid-main">
            @if($img0)<img src="{{ asset('storage/' . $img0) }}" alt="{{ $accommodation->name }}" onclick="openPhotoModal()">
            @else<div style="height:100%;background:var(--bnb-bg-light);display:flex;align-items:center;justify-content:center;font-size:5rem;">🏠</div>@endif
        </div>
        @foreach([$img1, $img2, $img3, $img4] as $sideImg)
        <div class="bnb-photo-grid-wrap">
            @if($sideImg)<img src="{{ asset('storage/' . $sideImg) }}" alt="{{ $accommodation->name }}" onclick="openPhotoModal()">
            @elseif($img0)<img src="{{ asset('storage/' . $img0) }}" alt="{{ $accommodation->name }}" onclick="openPhotoModal()" style="filter:brightness(.6)">
            @else<div style="height:100%;background:var(--bnb-bg-light);"></div>@endif
        </div>
        @endforeach
    </div>
    @if($allImages->count() > 0)
    <button class="bnb-show-all-btn" data-bs-toggle="modal" data-bs-target="#photoModal">
        <i class="bi bi-grid-3x3-gap"></i> نمایش همه تصاویر ({{ $allImages->count() }})
    </button>
    @endif
</div>

{{-- TITLE ROW --}}
<div class="d-flex justify-content-between align-items-start mt-4 mb-1" style="flex-wrap:wrap;gap:12px;">
    <div>
        <h1 style="font-size:22px;font-weight:700;color:var(--bnb-dark);margin-bottom:4px;">{{ $accommodation->name }}</h1>
        <div style="font-size:14px;color:var(--bnb-gray);">
            @if($accommodation->averageRating() > 0)
                <i class="bi bi-star-fill" style="color:var(--bnb-dark);font-size:12px;"></i>
                <strong style="color:var(--bnb-dark);">{{ $accommodation->averageRating() }}</strong>
                <span>({{ $accommodation->reviewCount() }} نظر)</span>
                <span style="margin:0 6px;">·</span>
            @endif
            <span>{{ $accommodation->city->province->name ?? '' }}، {{ $accommodation->city->name ?? '' }}</span>
            @if($accommodation->address)<span style="margin:0 6px;">·</span><span>{{ $accommodation->address }}</span>@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="bnb-filter-pill" onclick="toggleWishlist(this, {{ $accommodation->id }})"><i class="bi bi-heart me-1"></i>ذخیره</button>
        <button class="bnb-filter-pill" onclick="if(navigator.share) navigator.share({title:'{{ $accommodation->name }}',url:window.location.href})"><i class="bi bi-share me-1"></i>اشتراک</button>
    </div>
</div>

{{-- STICKY TABS --}}
<div class="bnb-sticky-tabs" id="stickyTabs">
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
        <div id="sec-info" class="d-flex gap-4 py-5 border-bottom" style="flex-wrap:wrap;">
            <div style="text-align:center;min-width:80px;"><i class="bi bi-people-fill" style="font-size:1.5rem;"></i><div style="font-size:14px;font-weight:600;margin-top:4px;">{{ $accommodation->capacity }} نفر</div><div style="font-size:12px;color:var(--bnb-gray);">ظرفیت</div></div>
            <div style="text-align:center;min-width:80px;"><i class="bi bi-door-open-fill" style="font-size:1.5rem;"></i><div style="font-size:14px;font-weight:600;margin-top:4px;">{{ $accommodation->rooms }}</div><div style="font-size:12px;color:var(--bnb-gray);">اتاق</div></div>
            <div style="text-align:center;min-width:80px;"><i class="bi bi-building" style="font-size:1.5rem;"></i><div style="font-size:14px;font-weight:600;margin-top:4px;">{{ $accommodation->typeLabel() }}</div><div style="font-size:12px;color:var(--bnb-gray);">نوع</div></div>
            @if(in_array('مناسب ویلچر', $accommodation->amenities ?? []))
            <div style="text-align:center;min-width:80px;"><i class="bi bi-wheelchair" style="font-size:1.5rem;color:var(--bnb-red);"></i><div style="font-size:14px;font-weight:600;margin-top:4px;">دسترسی</div><div style="font-size:12px;color:var(--bnb-gray);">ویلچر</div></div>
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
                        <div style="font-size:15px;font-weight:600;color:var(--bnb-dark);margin-bottom:8px;">{{ $roomType->name }}</div>
                        <div style="font-size:13px;color:var(--bnb-gray);display:flex;flex-wrap:wrap;gap:12px;">
                            @if($roomType->bed_type)<span><i class="bi bi-moon-stars me-1"></i>{{ $roomType->bed_type }}</span>@endif
                            <span><i class="bi bi-people me-1"></i>{{ $roomType->capacity }} نفر</span>
                            @if($roomType->size_sqm)<span><i class="bi bi-aspect-ratio me-1"></i>{{ $roomType->size_sqm }} متر مربع</span>@endif
                            <span>@if($roomType->has_private_bathroom)<i class="bi bi-check-circle-fill text-success me-1"></i>حمام اختصاصی@else<i class="bi bi-x-circle text-muted me-1"></i>بدون حمام اختصاصی@endif</span>
                            <span>@if(!$roomType->smoking)<i class="bi bi-slash-circle text-success me-1"></i>غیرسیگاری@else<i class="bi bi-fire text-warning me-1"></i>سیگاری@endif</span>
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
                    <div style="text-align:center;white-space:nowrap;">
                        <div style="font-size:16px;font-weight:700;color:var(--bnb-dark);">{{ number_format($rate->price_per_night) }}</div>
                        <div style="font-size:11px;color:var(--bnb-gray);">تومان / شب</div>
                    </div>
                    <div>
                        @auth
                        <form class="room-reserve-form" action="{{ route('bookings.store', $accommodation) }}" method="POST">
                            @csrf
                            <input type="hidden" name="room_type_id" value="{{ $roomType->id }}">
                            <input type="hidden" name="room_rate_id" value="{{ $rate->id }}">
                            <input type="hidden" name="check_in" class="rt-check-in">
                            <input type="hidden" name="check_out" class="rt-check-out">
                            <input type="hidden" name="guests" value="{{ $roomType->capacity }}">
                            <button type="button" class="btn-bnb" style="white-space:nowrap;" onclick="reserveRoom(this)">رزرو</button>
                        </form>
                        @else
                        <a href="{{ route('auth.mobile') }}" class="btn-bnb" style="text-decoration:none;display:block;white-space:nowrap;">ورود برای رزرو</a>
                        @endauth
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
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
                <i class="bi bi-star-fill" style="color:var(--bnb-dark);font-size:22px;"></i>
                <span style="font-size:22px;font-weight:700;color:var(--bnb-dark);">{{ $accommodation->averageRating() }}</span>
                <span style="font-size:14px;color:var(--bnb-gray);">({{ $accommodation->reviewCount() }} نظر)</span>
            </div>
            @endif
            <h2 style="font-size:18px;font-weight:700;color:var(--bnb-dark);margin-bottom:20px;">نظرات مهمانان</h2>
            @if(session('status'))<div class="bnb-alert bnb-alert-success mb-4">{{ session('status') }}</div>@endif
            @if($errors->has('rating'))<div class="bnb-alert bnb-alert-danger mb-4">{{ $errors->first('rating') }}</div>@endif
            @auth
                @if($canReview && !$userReview)
                <div class="bnb-room-card mb-5"><div style="padding:20px;">
                    <h3 style="font-size:16px;font-weight:600;margin-bottom:16px;">ثبت نظر</h3>
                    <form action="{{ route('reviews.store', $accommodation) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:8px;">امتیاز</label>
                            <div class="bnb-star-selector" id="starSelector">
                                @for($r=1;$r<=5;$r++)<i class="bi bi-star" data-val="{{ $r }}"></i>@endfor
                            </div>
                            <input type="hidden" name="rating" id="ratingInput" value="5">
                        </div>
                        <div class="mb-3">
                            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:8px;">نظر شما</label>
                            <textarea name="comment" class="bnb-select" rows="4" style="resize:vertical;" placeholder="تجربه اقامت خود را بنویسید...">{{ old('comment') }}</textarea>
                            @error('comment')<div style="color:var(--bnb-red);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn-bnb">ثبت نظر</button>
                    </form>
                </div></div>
                @elseif($userReview)
                <div class="bnb-alert bnb-alert-success mb-4"><i class="bi bi-check-circle me-2"></i>شما قبلاً نظر خود را ثبت کرده‌اید.</div>
                @endif
            @endauth
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                @forelse($reviews as $review)
                <div data-aos="fade-up">
                    <div style="display:flex;gap:12px;align-items:center;margin-bottom:8px;">
                        <div style="width:40px;height:40px;border-radius:50%;background:var(--bnb-bg-light);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:700;color:var(--bnb-dark);flex-shrink:0;">{{ mb_substr($review->user->name ?? '؟', 0, 1) }}</div>
                        <div><div style="font-size:14px;font-weight:600;color:var(--bnb-dark);">{{ $review->user->name ?? '' }}</div><div style="font-size:12px;color:var(--bnb-gray);">{{ $review->created_at->diffForHumans() }}</div></div>
                        <div style="margin-right:auto;">@for($s=1;$s<=5;$s++)<i class="bi bi-star{{ $s <= $review->rating ? '-fill' : '' }}" style="color:var(--bnb-dark);font-size:11px;"></i>@endfor</div>
                    </div>
                    <p style="font-size:14px;color:var(--bnb-dark);line-height:1.6;margin-bottom:8px;">{{ $review->comment }}</p>
                    @if($review->host_reply)
                    <div style="background:var(--bnb-bg-light);border-radius:8px;padding:12px;margin-top:8px;">
                        <div style="font-size:12px;font-weight:600;color:var(--bnb-dark);margin-bottom:4px;"><i class="bi bi-reply-fill me-1"></i>پاسخ میزبان</div>
                        <p style="font-size:13px;color:var(--bnb-gray);margin:0;">{{ $review->host_reply }}</p>
                    </div>
                    @endif
                </div>
                @empty
                <div style="grid-column:1/-1;text-align:center;padding:32px;color:var(--bnb-gray);"><i class="bi bi-chat-square" style="font-size:2.5rem;display:block;margin-bottom:8px;"></i>هنوز نظری ثبت نشده است.</div>
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

    {{-- RIGHT: Booking Widget --}}
    <div class="bnb-show-right">
        <div class="bnb-book-widget" id="sec-book">
            <div class="bnb-book-price">
                {{ number_format($accommodation->price_per_night) }} <span>تومان / شب</span>
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
            <form action="{{ route('bookings.store', $accommodation) }}" method="POST" id="bookingForm">
                @csrf
                <div class="bnb-book-fields">
                    <div class="bnb-book-field" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#showDateCal">
                        <label>تاریخ ورود و خروج</label>
                        <div id="showDateDisplay" class="picker-trigger"><span style="color:var(--bnb-gray);">انتخاب تاریخ</span></div>
                    </div>
                    <div class="collapse" id="showDateCal" style="border-bottom:1px solid var(--bnb-border);">
                        <div style="padding:8px;">
                            <div style="font-size:12px;color:var(--bnb-gray);margin-bottom:4px;padding:0 6px;"><i class="bi bi-info-circle me-1"></i>کلیک اول: ورود — کلیک دوم: خروج</div>
                            <div class="range-picker-cal"><div id="showCalEl"></div></div>
                        </div>
                    </div>
                    <input type="hidden" name="check_in" id="checkIn" value="{{ request('check_in', old('check_in')) }}">
                    <input type="hidden" name="check_out" id="checkOut" value="{{ request('check_out', old('check_out')) }}">
                    <div class="bnb-book-field">
                        <label>تعداد مهمان</label>
                        <select name="guests" class="picker-trigger" style="border:none;outline:none;font-family:var(--bnb-font);font-size:14px;">
                            @for($i = 1; $i <= $accommodation->capacity; $i++)
                                <option value="{{ $i }}" {{ request('guests', 1) == $i ? 'selected' : '' }}>{{ $i }} مهمان</option>
                            @endfor
                        </select>
                    </div>
                </div>
                <div id="pricePreview" class="bnb-price-breakdown d-none">
                    <div class="bnb-price-row"><span>{{ number_format($accommodation->price_per_night) }} × <span id="nightsCount">0</span> شب</span><span id="basePrice">-</span></div>
                    @auth
                        @if(Auth::user()->discount_percentage > 0)
                        <div class="bnb-price-row" style="color:var(--bnb-red);"><span>تخفیف {{ Auth::user()->discount_percentage }}٪</span><span id="discountAmount">-</span></div>
                        @endif
                    @endauth
                    <div class="bnb-price-row total"><span>جمع کل</span><span id="totalPrice">-</span></div>
                </div>
                @auth
                    @if($roomTypes->isNotEmpty())
                    <a href="#sec-rooms" class="btn-bnb d-block text-decoration-none text-center mt-3" style="background:linear-gradient(to left, #E31C5F, var(--bnb-red));">مشاهده اتاق‌ها و رزرو</a>
                    @else
                    <button type="submit" class="btn-bnb w-100 mt-3" style="background:linear-gradient(to left, #E31C5F, var(--bnb-red));">ثبت رزرو</button>
                    @endif
                @else
                <a href="{{ route('auth.mobile') }}" class="btn-bnb d-block text-decoration-none text-center mt-3" style="background:linear-gradient(to left, #E31C5F, var(--bnb-red));"><i class="bi bi-phone me-1"></i>ورود برای رزرو</a>
                @endauth
            </form>
            <p style="font-size:12px;color:var(--bnb-gray);text-align:center;margin-top:12px;"><i class="bi bi-shield-check me-1"></i>تا زمان تأیید نهایی، هیچ مبلغی دریافت نمی‌شود</p>
        </div>
    </div>

</div>{{-- /layout --}}
</div>{{-- /container --}}

{{-- Photo Modal --}}
<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" style="font-size:15px;font-weight:600;">{{ $accommodation->name }} — همه تصاویر</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" style="max-height:80vh;overflow-y:auto;">
                <div class="photo-grid">
                    @foreach($allImages as $img)<img src="{{ asset('storage/' . $img) }}" alt="{{ $accommodation->name }}" loading="lazy">@endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function openPhotoModal() { new bootstrap.Modal(document.getElementById('photoModal')).show(); }

function reserveRoom(btn) {
    var ci = document.getElementById('checkIn').value;
    var co = document.getElementById('checkOut').value;
    if (!ci || !co) { alert('لطفاً ابتدا تاریخ ورود و خروج را انتخاب کنید.'); document.getElementById('sec-book').scrollIntoView({behavior:'smooth',block:'center'}); return; }
    var form = btn.closest('form');
    form.querySelector('.rt-check-in').value = ci;
    form.querySelector('.rt-check-out').value = co;
    form.submit();
}

var pricePerNight = {{ $accommodation->price_per_night }};
var discountPct   = {{ auth()->check() ? auth()->user()->discount_percentage : 0 }};

function updatePrice() {
    var ci = $('#checkIn').val(), co = $('#checkOut').val();
    if (!ci || !co) { $('#pricePreview').addClass('d-none'); return; }
    var nights = Math.round((new Date(co) - new Date(ci)) / 86400000);
    if (nights <= 0) return;
    var base = pricePerNight * nights, discount = Math.round(base * discountPct / 100), total = base - discount;
    $('#nightsCount').text(nights);
    $('#basePrice').text(base.toLocaleString('fa-IR') + ' تومان');
    $('#discountAmount').text('-' + discount.toLocaleString('fa-IR') + ' تومان');
    $('#totalPrice').text(total.toLocaleString('fa-IR') + ' تومان');
    $('#pricePreview').removeClass('d-none');
}

var ciGreg = $('#checkIn').val(), coGreg = $('#checkOut').val();
initJalaliRange('#showCalEl', '#checkIn', '#checkOut', '#showDateDisplay', ciGreg, coGreg, function() {
    updatePrice();
    var colEl = document.getElementById('showDateCal');
    if (colEl) bootstrap.Collapse.getOrCreateInstance(colEl).hide();
});
if (ciGreg && coGreg) updatePrice();

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
    var active = 'sec-info';
    sections.forEach(function(id) { var el = document.getElementById(id); if (el && window.scrollY >= el.offsetTop - 120) active = id; });
    tabs.forEach(function(tab) { tab.classList.remove('active'); if (tab.getAttribute('href') === '#' + active) tab.classList.add('active'); });
});

function toggleWishlist(btn, id) {
    var icon = btn.querySelector('i');
    if (icon.classList.contains('bi-heart')) { icon.classList.replace('bi-heart','bi-heart-fill'); btn.style.color='var(--bnb-red)'; }
    else { icon.classList.replace('bi-heart-fill','bi-heart'); btn.style.color=''; }
}
</script>
@endpush
