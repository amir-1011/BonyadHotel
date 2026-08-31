<div>

<div x-on:manual-booking-rooms-selected.window="$wire.call('onAddRoomPhysicalSelected', $event.detail.rooms ?? [])">

@include('components.booking.show-details', [
    'booking' => $booking,
    'panel' => $panel,
    'pdfUrl' => route('admin.bookings.pdf', $booking),
])

@include('components.cancellation.status-card', ['booking' => $booking, 'panel' => $panel])

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
                    class="btn btn-sm btn-primary">ذخیره</button>
        </div>
    </div>
</div>

@include('components.booking.manage-details', ['booking' => $booking, 'panel' => $panel])

@include('components.booking.payment-capture-support')

<x-manual-booking.room-picker />

</div>
</div>
