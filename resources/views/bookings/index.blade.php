@extends('layouts.app')

@section('title', 'رزروهای من')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2 text-primary"></i>رزروهای من</h4>
    <a href="{{ route('home') }}" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-plus-circle me-1"></i>رزرو جدید
    </a>
</div>

@forelse($bookings as $booking)
    @php
        $isCompleted = $booking->status === 'confirmed' && $booking->check_out < now()->toDateString();
        $alreadyReviewed = isset($reviewedAccIds[$booking->accommodation_id]);
    @endphp
    <div class="card mb-3 shadow-sm {{ $isCompleted && !$alreadyReviewed ? 'border-warning border-2' : '' }}">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <h6 class="fw-bold mb-1">{{ $booking->accommodation->name }}</h6>
                    <p class="text-muted mb-1" style="font-size:.82rem">
                        <i class="bi bi-geo-alt me-1"></i>
                        {{ $booking->accommodation->city->province->name }} - {{ $booking->accommodation->city->name }}
                    </p>
                    <p class="mb-1" style="font-size:.82rem">
                        <i class="bi bi-calendar-range me-1"></i>
                        {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_in))->format('Y/m/d') }}
                        تا
                        {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_out))->format('Y/m/d') }}
                        | {{ $booking->nights }} شب | {{ $booking->guests }} نفر
                    </p>
                    <p class="text-muted mb-0" style="font-size:.8rem">
                        کد رهگیری: <span class="tracking-code">{{ $booking->tracking_code }}</span>
                    </p>
                </div>
                <div class="text-end flex-shrink-0">
                    <span class="badge bg-{{ $booking->statusColor() }} px-2 py-1 d-block mb-1">
                        {{ $booking->statusLabel() }}
                    </span>
                    @if($isCompleted)
                        @if($alreadyReviewed)
                            <span class="badge bg-success"><i class="bi bi-star-fill me-1"></i>نظر ثبت شده</span>
                        @else
                            <span class="badge bg-warning text-dark"><i class="bi bi-star me-1"></i>منتظر نظر شما</span>
                        @endif
                    @endif
                    @if($booking->discount_percentage > 0)
                        <div class="text-muted text-decoration-line-through mt-1" style="font-size:.78rem">{{ number_format($booking->base_price) }}</div>
                    @endif
                    <div class="price-tag">{{ number_format($booking->total_price) }}</div>
                    <div class="text-muted" style="font-size:.75rem">تومان</div>
                    @if($booking->discount_percentage > 0)
                        <span class="badge bg-success" style="font-size:.72rem">{{ $booking->discount_percentage }}٪ تخفیف</span>
                    @endif
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <a href="{{ route('bookings.show', $booking) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-eye me-1"></i>جزئیات
                </a>
                @if($booking->status === 'confirmed' && $booking->check_out >= now()->toDateString())
                    <form action="{{ route('bookings.cancel', $booking) }}" method="POST"
                          onsubmit="return confirm('آیا از لغو این رزرو مطمئن هستید؟')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-x-circle me-1"></i>لغو رزرو
                        </button>
                    </form>
                @endif
                @if($isCompleted && !$alreadyReviewed)
                    <button class="btn btn-warning btn-sm" type="button"
                            onclick="document.getElementById('review-form-{{ $booking->id }}').classList.toggle('d-none')">
                        <i class="bi bi-star me-1"></i>ثبت نظر
                    </button>
                @elseif($isCompleted && $alreadyReviewed)
                    <a href="{{ route('accommodations.show', $booking->accommodation) }}#reviews" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-chat-square-text me-1"></i>مشاهده نظر
                    </a>
                @endif
            </div>

            {{-- Inline review form (hidden by default) --}}
            @if($isCompleted && !$alreadyReviewed)
            <div id="review-form-{{ $booking->id }}" class="d-none mt-3 border-top pt-3">
                <h6 class="fw-bold mb-3"><i class="bi bi-star-fill text-warning me-2"></i>نظر خود را ثبت کنید</h6>
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
    <div class="text-center py-5">
        <i class="bi bi-calendar-x display-4 text-muted"></i>
        <p class="mt-3 text-muted fs-5">هنوز رزروی ثبت نکرده‌اید.</p>
        <a href="{{ route('home') }}" class="btn btn-primary btn-lg mt-2">
            <i class="bi bi-search me-1"></i>جستجو و رزرو اقامتگاه
        </a>
    </div>
@endforelse

{{ $bookings->links() }}
@endsection

@push('scripts')
<script>
// Star rating for inline review forms
document.querySelectorAll('[data-group][data-val]').forEach(star => {
    star.addEventListener('click', function () {
        const group = this.dataset.group;
        const val = parseInt(this.dataset.val);
        document.getElementById('rating-' + group).value = val;
        updateStars(group, val);
    });
    star.addEventListener('mouseenter', function () {
        updateStars(this.dataset.group, parseInt(this.dataset.val));
    });
});
document.querySelectorAll('[id^="stars-"]').forEach(container => {
    container.addEventListener('mouseleave', function () {
        const group = this.id.replace('stars-', '');
        const val = parseInt(document.getElementById('rating-' + group).value) || 5;
        updateStars(group, val);
    });
});
function updateStars(group, val) {
    document.querySelectorAll('.rating-star-' + group).forEach((s, i) => {
        s.className = 'bi bi-star' + (i < val ? '-fill' : '') + ' rating-star-' + group + ' text-warning';
    });
}
// Init each star group to current hidden input value
document.querySelectorAll('[id^="rating-"]').forEach(input => {
    const group = input.id.replace('rating-', '');
    const val = parseInt(input.value) || 5;
    updateStars(group, val);
});
</script>
@endpush
