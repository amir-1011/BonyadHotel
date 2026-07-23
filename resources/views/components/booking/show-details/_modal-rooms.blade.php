@if($hasRoomLines)
<x-booking.room-lines-table :booking="$booking" />
@elseif($booking->roomType)
<ul class="list-group list-group-flush">
    <li class="list-group-item d-flex justify-content-between px-0">
        <span class="text-muted">نوع اتاق</span>
        <strong>{{ $booking->roomType->name }}</strong>
    </li>
    @if($booking->roomRate)
    <li class="list-group-item d-flex justify-content-between px-0">
        <span class="text-muted">تعرفه</span>
        <span>{{ $booking->roomRate->name }} · {{ number_format($booking->roomRate->price_per_night) }} ت/شب/تخت</span>
    </li>
    @endif
</ul>
@else
<p class="text-muted mb-0">اطلاعات اتاق ثبت نشده است.</p>
@endif
