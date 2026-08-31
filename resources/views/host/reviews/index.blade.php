<div>

@php($hostUser = auth()->user())
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="ta-filter-stats mb-2">
            <div class="ta-filter-stat">
                <i class="bi bi-chat-square-text"></i>
                <span class="ta-filter-stat-value">{{ \App\Support\PdfPersian::toPersianDigits(number_format($stats['total'])) }}</span>
                <span class="ta-filter-stat-label">کل نظرات</span>
            </div>
            <div class="ta-filter-stat">
                <i class="bi bi-hourglass-split"></i>
                <span class="ta-filter-stat-value">{{ \App\Support\PdfPersian::toPersianDigits(number_format($stats['pending'])) }}</span>
                <span class="ta-filter-stat-label">بی‌پاسخ</span>
            </div>
            <div class="ta-filter-stat">
                <i class="bi bi-reply-fill"></i>
                <span class="ta-filter-stat-value">{{ \App\Support\PdfPersian::toPersianDigits(number_format($stats['replied'])) }}</span>
                <span class="ta-filter-stat-label">پاسخ داده شده</span>
            </div>
            <div class="ta-filter-stat">
                <i class="bi bi-star-fill"></i>
                <span class="ta-filter-stat-value">{{ $stats['avg'] ? \App\Support\PdfPersian::toPersianDigits(number_format($stats['avg'], 1)) : '—' }}</span>
                <span class="ta-filter-stat-label">میانگین امتیاز</span>
            </div>
        </div>
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <select wire:model.live="accommodationId" class="form-select form-select-sm">
                    <option value="0">همه اقامتگاه‌ها</option>
                    @foreach($myAccommodations as $a)
                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select wire:model.live="rating" class="form-select form-select-sm">
                    <option value="0">همه امتیازها</option>
                    @for($i=5;$i>=1;$i--)
                    <option value="{{ $i }}">{{ $i }} ستاره</option>
                    @endfor
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select wire:model.live="replied" class="form-select form-select-sm">
                    <option value="">همه</option>
                    <option value="0">بی‌پاسخ</option>
                    <option value="1">پاسخ داده شده</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <input wire:model.live="search" type="text" class="form-control form-control-sm" placeholder="جستجو...">
            </div>
        </div>
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
                @if($hostUser->hostCanAny('accommodations.edit', ['read', 'edit']))
                <a wire:navigate href="{{ route('host.accommodations.edit', $review->accommodation) }}" class="badge bg-primary text-decoration-none">
                    <i class="bi bi-building me-1"></i>{{ Str::limit($review->accommodation->name ?? '', 25) }}
                </a>
                @else
                <span class="badge bg-secondary">
                    <i class="bi bi-building me-1"></i>{{ Str::limit($review->accommodation->name ?? '', 25) }}
                </span>
                @endif
                @if($review->booking_id && $hostUser->hostCan('bookings.show', 'read'))
                <a wire:navigate href="{{ route('host.bookings.show', $review->booking_id) }}" class="badge bg-secondary text-decoration-none">
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
                <x-host.can page="reviews.list" action="edit">
                <button wire:click="startReply({{ $review->id }})" class="btn btn-sm btn-outline-success">
                    <i class="bi bi-pencil me-1"></i>ویرایش پاسخ
                </button>
                </x-host.can>
                <x-host.can page="reviews.list" action="delete">
                <button wire:click="deleteReply({{ $review->id }})" data-swal-confirm="پاسخ حذف شود؟" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash me-1"></i>حذف پاسخ
                </button>
                </x-host.can>
            </div>
        </div>
        @endif

        {{-- Reply form (shown when this review is being replied to) --}}
        @if($replyingTo === $review->id)
        <div>
            <form wire:submit.prevent="submitReply">
                <div class="mb-2">
                    <label class="form-label fw-semibold small">
                        <i class="bi bi-reply-fill text-success me-1"></i>
                        {{ $review->host_reply ? 'ویرایش پاسخ' : 'ثبت پاسخ به مهمان' }}
                    </label>
                    <textarea wire:model="replyText" class="form-control" rows="3"
                        placeholder="پاسخ خود را به این نظر بنویسید..."></textarea>
                    @error('replyText')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-send me-1"></i>ارسال پاسخ
                    </button>
                    <button type="button" wire:click="$set('replyingTo', null)" class="btn btn-outline-secondary btn-sm">
                        انصراف
                    </button>
                </div>
            </form>
        </div>
        @elseif(!$review->host_reply)
        <x-host.can page="reviews.list" action="edit">
        <button wire:click="startReply({{ $review->id }})" class="btn btn-sm btn-outline-success">
            <i class="bi bi-reply me-1"></i>ثبت پاسخ
        </button>
        </x-host.can>
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


</div>

@push('scripts')
<script>
function toggleReplyForm(id) {
    const form = document.getElementById('reply-form-' + id);
    form.style.display = form.style.display === 'none' ? '' : 'none';
}
</script>
@endpush
