<div>

@php($hostUser = Auth::user())

<div x-on:manual-booking-rooms-selected.window="$wire.call('onAddRoomPhysicalSelected', $event.detail.rooms ?? [])">

@include('components.booking.show-details', [
    'booking' => $booking,
    'panel' => $panel,
    'pdfUrl' => $hostUser->hostCan('bookings.pdf', 'read') ? route('host.bookings.pdf', $booking) : null,
])

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

@include('components.booking.payment-capture-support')

<x-manual-booking.room-picker />

</div>
</div>
