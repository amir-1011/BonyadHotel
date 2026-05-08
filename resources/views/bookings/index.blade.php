@extends('layouts.app')

@section('title', 'رزروهای من')

@section('content')
<div class="container-xxl px-3 px-lg-4" style="padding-top:32px;padding-bottom:48px;">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 style="font-size:22px;font-weight:700;color:var(--bnb-dark);margin:0;">رزروهای من</h1>
    <a href="{{ route('home') }}" class="bnb-filter-pill text-decoration-none"><i class="bi bi-plus-circle me-1"></i>رزرو جدید</a>
</div>

@forelse($bookings as $booking)
    @php
        $isCompleted = $booking->status === 'confirmed' && $booking->check_out < now()->toDateString();
        $alreadyReviewed = isset($reviewedAccIds[$booking->accommodation_id]);
    @endphp
    <div style="border:1px solid {{ $isCompleted && !$alreadyReviewed ? 'var(--bnb-red)' : 'var(--bnb-border)' }};border-radius:12px;overflow:hidden;margin-bottom:16px;background:#fff;" data-aos="fade-up">
        <div style="padding:20px;">
            <div class="d-flex justify-content-between align-items-start" style="flex-wrap:wrap;gap:12px;">
                <div>
                    <div style="font-size:16px;font-weight:600;color:var(--bnb-dark);margin-bottom:4px;">{{ $booking->accommodation->name }}</div>
                    <div style="font-size:13px;color:var(--bnb-gray);margin-bottom:2px;"><i class="bi bi-geo-alt me-1"></i>{{ $booking->accommodation->city->province->name }} — {{ $booking->accommodation->city->name }}</div>
                    <div style="font-size:13px;color:var(--bnb-gray);margin-bottom:2px;">
                        <i class="bi bi-calendar-range me-1"></i>
                        {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_in))->format('Y/m/d') }}
                        تا
                        {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_out))->format('Y/m/d') }}
                        &middot; {{ $booking->nights }} شب &middot; {{ $booking->guests }} نفر
                    </div>
                    <div style="font-size:12px;color:var(--bnb-gray);"><i class="bi bi-hash me-1"></i>کد رهگیری: <strong style="color:var(--bnb-dark);">{{ $booking->tracking_code }}</strong></div>
                </div>
                <div style="text-align:left;">
                    @php $statusColors = ['confirmed'=>'green','pending'=>'orange','cancelled'=>'var(--bnb-red)']; $sc = $statusColors[$booking->status] ?? 'var(--bnb-gray)'; @endphp
                    <span style="display:inline-block;background:{{ $sc }}1a;color:{{ $sc }};border:1px solid {{ $sc }}40;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600;margin-bottom:8px;">{{ $booking->statusLabel() }}</span>
                    @if($booking->discount_percentage > 0)<div style="font-size:12px;text-decoration:line-through;color:var(--bnb-gray);">{{ number_format($booking->base_price) }}</div>@endif
                    <div style="font-size:18px;font-weight:700;color:var(--bnb-dark);">{{ number_format($booking->total_price) }} <span style="font-size:12px;font-weight:400;color:var(--bnb-gray);">تومان</span></div>
                    @if($booking->discount_percentage > 0)<div style="font-size:12px;color:var(--bnb-red);">{{ $booking->discount_percentage }}٪ تخفیف</div>@endif
                </div>
            </div>
            <div style="border-top:1px solid var(--bnb-border);margin-top:16px;padding-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <a href="{{ route('bookings.show', $booking) }}" class="bnb-filter-pill text-decoration-none"><i class="bi bi-eye me-1"></i>جزئیات</a>
                @if($booking->status === 'confirmed' && $booking->check_out >= now()->toDateString())
                <form action="{{ route('bookings.cancel', $booking) }}" method="POST" onsubmit="return confirm('آیا از لغو این رزرو مطمئن هستید؟')">
                    @csrf
                    <button type="submit" class="bnb-filter-pill" style="border-color:var(--bnb-red);color:var(--bnb-red);background:none;cursor:pointer;font-family:var(--bnb-font);"><i class="bi bi-x-circle me-1"></i>لغو</button>
                </form>
                @endif
                @if($isCompleted && !$alreadyReviewed)
                <button class="bnb-filter-pill" style="border-color:var(--bnb-dark);background:var(--bnb-dark);color:#fff;cursor:pointer;font-family:var(--bnb-font);" type="button" onclick="document.getElementById('review-form-{{ $booking->id }}').classList.toggle('d-none')">
                    <i class="bi bi-star me-1"></i>ثبت نظر
                </button>
                @elseif($isCompleted && $alreadyReviewed)
                <a href="{{ route('accommodations.show', $booking->accommodation) }}#reviews" class="bnb-filter-pill text-decoration-none" style="color:green;border-color:green;"><i class="bi bi-star-fill me-1"></i>نظر ثبت شده</a>
                @endif
            </div>

            @if($isCompleted && !$alreadyReviewed)
            <div id="review-form-{{ $booking->id }}" class="d-none" style="padding:20px;border-top:1px solid var(--bnb-border);">
                <h6 style="font-size:15px;font-weight:600;margin-bottom:16px;"><i class="bi bi-star-fill me-2" style="color:var(--bnb-dark);"></i>نظر خود را ثبت کنید</h6>
                <form action="{{ route('reviews.store', $booking->accommodation) }}" method="POST">
                    @csrf
                    <input type="hidden" name="booking_id" value="{{ $booking->id }}">
                    {{-- Star rating --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">امتیاز شما</label>
                        <div class="d-flex gap-2 fs-3" id="stars-{{ $booking->id }}">
                            @for($r = 1; $r <= 5; $r++)
                            <i class="bi bi-star rating-star-{{ $booking->id }} text-warning"
                               role="button"
                               data-val="{{ $r }}"
                               data-group="{{ $booking->id }}"
                               style="cursor:pointer"></i>
                            @endfor
                        </div>
                        <input type="hidden" name="rating" id="rating-{{ $booking->id }}" value="5">
                    </div>
                    {{-- Comment --}}
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">نظر شما (اختیاری)</label>
                        <textarea name="comment" class="form-control" rows="3"
                            placeholder="تجربه اقامت خود را با دیگران به اشتراک بگذارید..."></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-warning btn-sm px-4">
                            <i class="bi bi-send me-1"></i>ارسال نظر
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                onclick="document.getElementById('review-form-{{ $booking->id }}').classList.add('d-none')">
                            انصراف
                        </button>
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>
@empty
    <div style="text-align:center;padding:60px 20px;">
        <div style="font-size:64px;margin-bottom:16px;">📅</div>
        <h5 style="color:var(--bnb-dark);font-weight:600;">هنوز رزروی ندارید</h5>
        <p style="color:var(--bnb-gray);">اقامتگاه موردنظرتان را پیدا کنید و اولین رزروتان را ثبت کنید!</p>
        <a href="{{ route('home') }}" class="btn-bnb" style="display:inline-block;text-decoration:none;margin-top:8px;"><i class="bi bi-search me-1"></i>جستجو اقامتگاه</a>
    </div>
@endforelse

<div class="mt-3">{{ $bookings->links() }}</div>
</div>
@endsection

@push('scripts')
<style>
.bnb-star-selector { display: flex; gap: 4px; font-size: 22px; cursor: pointer; }
.bnb-star-selector .bi { color: var(--bnb-dark); transition: color .15s; }
</style>
<script>
document.querySelectorAll('[data-group][data-val]').forEach(function(star) {
    star.addEventListener('click', function() { var g = this.dataset.group; var v = parseInt(this.dataset.val); document.getElementById('rating-'+g).value = v; updateStars(g, v); });
    star.addEventListener('mouseenter', function() { updateStars(this.dataset.group, parseInt(this.dataset.val)); });
});
document.querySelectorAll('[id^="stars-"]').forEach(function(container) {
    container.addEventListener('mouseleave', function() { var g = this.id.replace('stars-',''); updateStars(g, parseInt(document.getElementById('rating-'+g).value)||5); });
});
function updateStars(group, val) {
    document.querySelectorAll('#stars-'+group+' .bi').forEach(function(s, i) { s.className = 'bi bi-star'+(i < val ? '-fill' : ''); });
}
document.querySelectorAll('[id^="rating-"]').forEach(function(input) { var g = input.id.replace('rating-',''); updateStars(g, parseInt(input.value)||5); });
</script>
@endpush
