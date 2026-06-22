<div>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a wire:navigate href="{{ route('host.bookings.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">رزرو {{ $booking->tracking_code }}</h5>
    <span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span>
    @if($booking->isManual())
    <span class="badge bg-info text-dark">رزرو دستی</span>
    @endif
    <a href="{{ route('host.bookings.pdf', $booking) }}" target="_blank" class="btn btn-sm btn-outline-success ms-auto"><i class="bi bi-file-pdf me-1"></i>PDF</a>
</div>

@include('components.booking.show-details', ['booking' => $booking, 'panel' => $panel])

@if($booking->status === 'pending')
<div class="card shadow-sm mt-3">
    <div class="card-body d-flex gap-2">
        <button wire:click="confirm" class="btn btn-success flex-fill">تأیید</button>
        <button wire:click="cancel" data-swal-confirm="لغو شود؟" class="btn btn-danger flex-fill">لغو</button>
    </div>
</div>
@endif

@include('components.booking.manage-details', ['booking' => $booking, 'panel' => $panel])

</div>
