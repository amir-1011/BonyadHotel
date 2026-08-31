@props(['booking'])

@if($booking->isMedicalAccommodation())
    <br><span class="badge bg-info text-dark" style="font-size:.65rem">اسکان درمانی</span>
@endif
@if($booking->isCredit())
    <br><span class="badge bg-warning text-dark" style="font-size:.65rem">اعتباری</span>
@endif
@if(!$booking->billsAsRegularGuest() && $booking->user && (int) $booking->user->discount_percentage > 0)
    <br><span class="badge bg-warning text-dark" style="font-size:.65rem">{{ $booking->user->discount_percentage }}% تخفیف</span>
@endif
