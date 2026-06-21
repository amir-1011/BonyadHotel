<div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a wire:navigate href="{{ route('host.bookings.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">رزرو {{ $booking->tracking_code }}</h5>
    <span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span>
    <a href="{{ route('host.bookings.pdf', $booking) }}" target="_blank" class="btn btn-sm btn-outline-success ms-auto"><i class="bi bi-file-pdf me-1"></i>PDF</a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-calendar me-2"></i>اطلاعات رزرو</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">اقامتگاه</span><strong>{{ $booking->accommodation->name }}</strong></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تاریخ ورود / خروج</span><span>@jalali($booking->check_in) — @jalali($booking->check_out)</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">مهمان</span><span>{{ $booking->guests }} نفر · {{ $booking->nights }} شب</span></li>
                @if($booking->payment_method)
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">پرداخت</span><span>{{ $booking->paymentMethodLabel() }}</span></li>
                @endif
                <li class="list-group-item d-flex justify-content-between"><span class="fw-bold">مبلغ کل</span><strong class="text-success">{{ number_format($booking->total_price) }} ت</strong></li>
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-person me-2"></i>مهمان</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">نام</span><span>{{ $booking->guest_contact_name ?? $booking->user->name ?? '—' }}</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">موبایل</span><code>{{ $booking->guest_contact_mobile ?? $booking->user->mobile }}</code></li>
                @if($booking->veteran_type_applied || ($booking->user && $booking->user->veteran_type))
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">گروه ایثارگری</span><span>{{ $booking->veteran_type_applied ? $booking->veteranLabelApplied() : $booking->user->veteranLabel() }}</span></li>
                @endif
            </ul>
        </div>
        @if($booking->status === 'pending')
        <div class="card shadow-sm mt-3">
            <div class="card-body d-flex gap-2">
                <button wire:click="confirm" class="btn btn-success flex-fill">تأیید</button>
                <button wire:click="cancel" data-swal-confirm="لغو شود؟" class="btn btn-danger flex-fill">لغو</button>
            </div>
        </div>
        @endif
    </div>
</div>

@include('components.booking.manage-details', ['booking' => $booking, 'panel' => $panel])

</div>
