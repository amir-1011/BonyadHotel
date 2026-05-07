@extends('layouts.host')
@section('title', 'نظرات مهمانان')
@section('page-title', 'نظرات مهمانان')

@section('content')

{{-- Stats Row --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;flex-shrink:0">
                    <i class="bi bi-chat-square-text text-primary fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5">{{ $stats['total'] }}</div>
                    <div class="text-muted small">کل نظرات</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 bg-warning bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;flex-shrink:0">
                    <i class="bi bi-hourglass-split text-warning fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5">{{ $stats['pending'] }}</div>
                    <div class="text-muted small">بی‌پاسخ</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 bg-success bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;flex-shrink:0">
                    <i class="bi bi-reply-fill text-success fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5">{{ $stats['replied'] }}</div>
                    <div class="text-muted small">پاسخ داده شده</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width:44px;height:44px;flex-shrink:0">
                    <i class="bi bi-star-fill text-info fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5">{{ $stats['avg'] ? number_format($stats['avg'], 1) : '—' }}</div>
                    <div class="text-muted small">میانگین امتیاز</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <select name="accommodation_id" class="form-select form-select-sm">
                    <option value="">همه اقامتگاه‌ها</option>
                    @foreach($myAccommodations as $a)
                    <option value="{{ $a->id }}" {{ request('accommodation_id') == $a->id ? 'selected' : '' }}>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="rating" class="form-select form-select-sm">
                    <option value="">همه امتیازها</option>
                    @for($i=5;$i>=1;$i--)
                    <option value="{{ $i }}" {{ request('rating') == $i ? 'selected' : '' }}>{{ $i }} ستاره</option>
                    @endfor
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="replied" class="form-select form-select-sm">
                    <option value="">همه</option>
                    <option value="0" {{ request('replied')==='0' ? 'selected' : '' }}>بی‌پاسخ</option>
                    <option value="1" {{ request('replied')==='1' ? 'selected' : '' }}>پاسخ داده شده</option>
                </select>
            </div>
            <div class="col-6 col-md-2"><button class="btn btn-sm btn-success w-100">فیلتر</button></div>
            <div class="col-6 col-md-2"><a href="{{ route('host.reviews.index') }}" class="btn btn-sm btn-outline-secondary w-100">پاک کردن</a></div>
        </form>
    </div>
</div>

{{-- Reviews List --}}
@forelse($reviews as $review)
<div class="card shadow-sm mb-3 border-0 {{ $review->host_reply ? '' : 'border-start border-warning border-3' }}" id="review-{{ $review->id }}">
    <div class="card-body">

        {{-- Review Header --}}
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px">
                    <i class="bi bi-person text-primary fs-5"></i>
                </div>
                <div>
                    <div class="fw-bold">{{ $review->user->name ?? $review->user->mobile }}</div>
                    <div class="text-muted small">{{ $review->created_at->diffForHumans() }}</div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                {{-- Star rating --}}
                <div>
                    @for($s=1;$s<=5;$s++)
                        <i class="bi bi-star{{ $s <= $review->rating ? '-fill' : '' }} text-warning"></i>
                    @endfor
                    <span class="text-muted small me-1">({{ $review->rating }}/۵)</span>
                </div>
                {{-- Accommodation badge --}}
                <a href="{{ route('host.accommodations.edit', $review->accommodation) }}" class="badge bg-primary text-decoration-none">
                    <i class="bi bi-building me-1"></i>{{ Str::limit($review->accommodation->name ?? '', 25) }}
                </a>
                {{-- Booking link --}}
                @if($review->booking_id)
                <a href="{{ route('host.bookings.show', $review->booking_id) }}" class="badge bg-secondary text-decoration-none">
                    <i class="bi bi-calendar-check me-1"></i>رزرو
                </a>
                @endif
                {{-- Reply status badge --}}
                @if($review->host_reply)
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>پاسخ داده شده</span>
                @else
                    <span class="badge bg-warning text-dark"><i class="bi bi-hourglass me-1"></i>بی‌پاسخ</span>
                @endif
            </div>
        </div>

        {{-- Review text --}}
        <div class="bg-light rounded-3 p-3 mb-3">
            <i class="bi bi-chat-quote text-muted me-2"></i>
            {{ $review->comment ?? '(بدون متن)' }}
        </div>

        {{-- Existing host reply --}}
        @if($review->host_reply)
        <div class="bg-success bg-opacity-10 border-start border-success border-3 rounded-3 p-3 mb-3">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-reply-fill text-success"></i>
                <span class="fw-semibold text-success small">پاسخ شما</span>
                <span class="text-muted" style="font-size:.75rem">— {{ $review->host_replied_at->diffForHumans() }}</span>
            </div>
            <div>{{ $review->host_reply }}</div>
            <div class="mt-2 d-flex gap-2">
                <button class="btn btn-sm btn-outline-success" onclick="toggleReplyForm({{ $review->id }})">
                    <i class="bi bi-pencil me-1"></i>ویرایش پاسخ
                </button>
                <form action="{{ route('host.reviews.reply.delete', $review) }}" method="POST" onsubmit="return confirm('پاسخ حذف شود؟')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>حذف پاسخ
                    </button>
                </form>
            </div>
        </div>
        @endif

        {{-- Reply form (hidden if already replied, shown if not) --}}
        <div id="reply-form-{{ $review->id }}" {{ $review->host_reply ? 'style=display:none' : '' }}>
            <form action="{{ route('host.reviews.reply', $review) }}" method="POST">
                @csrf
                <div class="mb-2">
                    <label class="form-label fw-semibold small">
                        <i class="bi bi-reply-fill text-success me-1"></i>
                        {{ $review->host_reply ? 'ویرایش پاسخ' : 'ثبت پاسخ به مهمان' }}
                    </label>
                    <textarea name="host_reply" class="form-control @error('host_reply') is-invalid @enderror" rows="3"
                        placeholder="پاسخ خود را به این نظر بنویسید...">{{ old('host_reply', $review->host_reply) }}</textarea>
                    @error('host_reply')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-send me-1"></i>ارسال پاسخ
                    </button>
                    @if($review->host_reply)
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleReplyForm({{ $review->id }})">
                        انصراف
                    </button>
                    @endif
                </div>
            </form>
        </div>

        {{-- Show reply form button (if already replied) --}}
        @if(!$review->host_reply)
        {{-- already shown by default --}}
        @endif

    </div>
</div>
@empty
<div class="card shadow-sm">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-chat-square fs-1 d-block mb-3"></i>
        <h6>هیچ نظری یافت نشد</h6>
        <p class="small">وقتی مهمانان نظر بگذارند اینجا نمایش داده می‌شود.</p>
    </div>
</div>
@endforelse

{{-- Pagination --}}
<div class="mt-3">{{ $reviews->links() }}</div>

@endsection

@push('scripts')
<script>
function toggleReplyForm(id) {
    const form = document.getElementById('reply-form-' + id);
    form.style.display = form.style.display === 'none' ? '' : 'none';
}
</script>
@endpush
