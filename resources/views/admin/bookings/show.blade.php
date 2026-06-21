<div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a wire:navigate href="{{ route('admin.bookings.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">رزرو {{ $booking->tracking_code }}</h5>
    <span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span>
    @if($booking->isManual())
    <span class="badge bg-info text-dark">رزرو دستی</span>
    @endif
    <a href="{{ route('admin.bookings.pdf', $booking) }}" target="_blank" class="btn btn-sm btn-outline-success ms-auto"><i class="bi bi-file-pdf me-1"></i>PDF</a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-calendar-check me-2"></i>اطلاعات رزرو</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">کد پیگیری</span><code>{{ $booking->tracking_code }}</code></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">اقامتگاه</span><strong>{{ $booking->accommodation->name }}</strong></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تاریخ ورود</span><span>@jalali($booking->check_in)</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تاریخ خروج</span><span>@jalali($booking->check_out)</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تعداد شب / مهمان</span><span>{{ $booking->nights }} شب · {{ $booking->guests }} نفر</span></li>
                @if($booking->roomType)
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">اتاق</span><span>{{ $booking->roomType->name }}@if($booking->roomRate) — {{ $booking->roomRate->name }}@endif</span></li>
                @endif
                @if($booking->payment_method)
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">روش پرداخت</span><span>{{ $booking->paymentMethodLabel() }}</span></li>
                @endif
                @if($booking->createdBy)
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">ثبت توسط</span><span>{{ $booking->createdBy->name }}</span></li>
                @endif
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-person me-2"></i>رزرو‌کننده</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">نام</span><span>{{ $booking->guest_contact_name ?? $booking->user->name ?? '—' }}</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">موبایل</span><code>{{ $booking->guest_contact_mobile ?? $booking->user->mobile }}</code></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">گروه ایثارگری</span><span>{{ $booking->veteran_type_applied ? $booking->veteranLabelApplied() : $booking->user->veteranLabel() }}</span></li>
            </ul>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-cash-stack me-2"></i>جزئیات مالی</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">اقامت</span><span>{{ number_format($booking->roomSubtotal() + $booking->extra_guests_price) }} ت</span></li>
                @if($booking->services_subtotal > 0)
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">خدمات</span><span>{{ number_format($booking->services_subtotal) }} ت</span></li>
                @endif
                <li class="list-group-item d-flex justify-content-between small">
                    <span class="text-muted">تخفیف اقامت ({{ $booking->discount_percentage }}٪)</span>
                    <span class="text-danger">− {{ number_format(max(0, $booking->discount_amount - ($booking->services->sum('discount_amount') ?? 0))) }} ت</span>
                </li>
                @if(($booking->services->sum('discount_amount') ?? 0) > 0)
                <li class="list-group-item d-flex justify-content-between small">
                    <span class="text-muted">تخفیف خدمات</span>
                    <span class="text-danger">− {{ number_format($booking->services->sum('discount_amount')) }} ت</span>
                </li>
                @endif
                <li class="list-group-item d-flex justify-content-between"><span class="fw-bold">مبلغ نهایی</span><strong class="text-primary">{{ number_format($booking->total_price) }} ت</strong></li>
            </ul>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-gear me-2"></i>تغییر وضعیت</div>
            <div class="card-body">
                <div class="d-flex gap-2">
                    <select wire:model.live="selectedStatus" class="form-select form-select-sm">
                        <option value="pending">در انتظار</option>
                        <option value="confirmed">تأیید شده</option>
                        <option value="cancelled">لغو شده</option>
                    </select>
                    <button wire:click="updateStatus"
                            class="btn btn-sm btn-primary"
                            @if($selectedStatus === 'cancelled' && $booking->status !== 'cancelled') data-swal-confirm="رزرو لغو شود؟" @endif>ذخیره</button>
                </div>
            </div>
        </div>
    </div>
</div>

@include('components.booking.manage-details', ['booking' => $booking, 'panel' => $panel])

</div>
