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

@include('components.booking.show-details', ['booking' => $booking, 'panel' => $panel])

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

@include('components.booking.manage-details', ['booking' => $booking, 'panel' => $panel])

</div>
