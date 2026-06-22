@php
    $bid = $booking->id;
@endphp

<ul class="list-group list-group-flush">
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">کد پیگیری</span>
        <code dir="ltr">{{ $booking->tracking_code }}</code>
    </li>
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">وضعیت</span>
        <span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span>
    </li>
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">منبع ثبت</span>
        <span>{{ $booking->bookingSourceLabel() }}</span>
    </li>
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">اقامتگاه</span>
        <strong class="text-end">{{ $booking->accommodation->name }}</strong>
    </li>
    @if($booking->accommodation->city)
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">شهر / استان</span>
        <span class="text-end">
            {{ $booking->accommodation->city->name }}
            @if($booking->accommodation->city->province)
                · {{ $booking->accommodation->city->province->name }}
            @endif
        </span>
    </li>
    @endif
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">تاریخ ورود</span>
        <span dir="ltr">@jalali($booking->check_in)</span>
    </li>
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">تاریخ خروج</span>
        <span dir="ltr">@jalali($booking->check_out)</span>
    </li>
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">مدت اقامت</span>
        <span><strong>{{ $booking->nights }}</strong> شب</span>
    </li>
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">تعداد مهمان</span>
        <span>
            <strong>{{ $booking->guests }}</strong> نفر
            @if(($booking->children_under_6 ?? 0) > 0)
                <span class="text-muted">(شامل {{ $booking->children_under_6 }} کودک زیر ۶ سال)</span>
            @endif
        </span>
    </li>
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">تخت / صورتحساب</span>
        <span>{{ $booking->billingGuests() }} تخت · {{ $booking->rooms_consumed }} اتاق مصرف‌شده</span>
    </li>
    @if($booking->extra_guests > 0)
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">کف‌خواب</span>
        <span>{{ $booking->extra_guests }} نفر · {{ number_format($booking->extra_guests_price) }} تومان</span>
    </li>
    @endif
    @if($booking->bill_full_rooms)
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">رزرو کامل اتاق</span>
        <span class="badge text-bg-secondary">بله</span>
    </li>
    @endif
    @if($booking->payment_method)
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">روش پرداخت</span>
        <span>{{ $booking->paymentMethodLabel() }}</span>
    </li>
    @endif
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">تاریخ ثبت</span>
        <span dir="ltr">@jalali($booking->created_at)</span>
    </li>
    @if($booking->createdBy && $panel === 'admin')
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">ثبت توسط</span>
        <span>{{ $booking->createdBy->name }}</span>
    </li>
    @endif
</ul>
