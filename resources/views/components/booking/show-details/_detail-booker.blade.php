<ul class="list-group list-group-flush">
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">نام</span>
        <strong>{{ $booking->bookerName() }}</strong>
    </li>
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">موبایل</span>
        <code dir="ltr">{{ $booking->bookerMobile() }}</code>
    </li>
    @if($booking->bookerNationalId())
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">کد ملی</span>
        <code dir="ltr">{{ $booking->bookerNationalId() }}</code>
    </li>
    @endif
    @if($booking->user_id)
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">شناسه کاربر</span>
        <span dir="ltr">#{{ $booking->user_id }}</span>
    </li>
    @endif
    @if($bookerManualDiscount)
    <li class="list-group-item px-0">
        <span class="text-muted d-block mb-1">تخفیف دستی اقامت</span>
        <span class="badge text-bg-info">{{ $bookerManualDiscount->manual_discount_percentage }}٪</span>
        @if($bookerManualDiscount->manual_discount_reason)
        <div class="text-muted mt-1" style="font-size:.78rem">{{ $bookerManualDiscount->manual_discount_reason }}</div>
        @endif
    </li>
    @elseif($bookerGuest && $bookerGuest->manual_discount_percentage)
    <li class="list-group-item px-0">
        <span class="text-muted d-block mb-1">تخفیف دستی اقامت</span>
        <span class="badge text-bg-info">{{ $bookerGuest->manual_discount_percentage }}٪</span>
        @if($bookerGuest->manual_discount_reason)
        <div class="text-muted mt-1" style="font-size:.78rem">{{ $bookerGuest->manual_discount_reason }}</div>
        @endif
    </li>
    @endif
</ul>
