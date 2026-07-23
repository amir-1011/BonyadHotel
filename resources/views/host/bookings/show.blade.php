<div>

@php($hostUser = Auth::user())

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a wire:navigate href="{{ route('host.bookings.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span>
    @if($booking->isManual())
    <span class="badge bg-info text-dark">رزرو دستی</span>
    @endif
    @if($hostUser->hostCan('bookings.pdf', 'read'))
    <a href="{{ route('host.bookings.pdf', $booking) }}" target="_blank" class="btn btn-sm btn-outline-success ms-auto"><i class="bi bi-file-pdf me-1"></i>PDF</a>
    @endif
</div>

@include('components.booking.show-details', ['booking' => $booking, 'panel' => $panel])

@include('components.cancellation.status-card', ['booking' => $booking, 'panel' => $panel])

@if($booking->status === 'pending' && $booking->canEditBookingDetails() && $hostUser->hostCan('bookings.show', 'edit'))
<div class="card shadow-sm mt-3">
    <div class="card-body d-flex gap-2">
        <button wire:click="confirm" class="btn btn-success flex-fill">تأیید</button>
        <button wire:click="cancel" data-swal-confirm="لغو شود؟" class="btn btn-danger flex-fill">لغو</button>
    </div>
</div>
@endif

@include('components.booking.manage-details', ['booking' => $booking, 'panel' => $panel])

</div>
