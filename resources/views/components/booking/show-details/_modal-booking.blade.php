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
        <span>{{ $booking->extra_guests }} نفر · {{ \App\Support\PdfPersian::toPersianDigits(number_format($booking->extra_guests_price)) }} ریال</span>
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
    @if($booking->isMedicalAccommodation())
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">شماره قرارداد</span>
        <span dir="ltr">{{ $booking->medicalContractNumber() ?: '—' }}</span>
    </li>
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">تعرفه اسکان درمانی</span>
        <span>{{ $booking->medicalTariffLabel() ?: '—' }}</span>
    </li>
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">کارفرما / بیمه‌گر</span>
        <span>{{ $booking->employer?->name ?? 'بیمه دی' }}</span>
    </li>
    @endif
    @if($booking->isMedicalAccommodation() && $booking->hasMedicalReferralLetters())
    <li class="list-group-item d-flex justify-content-between align-items-start gap-2 px-0">
        <span class="text-muted">معرفی‌نامه اسکان درمانی</span>
        <x-booking.authorized-document-links
            :urls="collect($booking->medicalReferralLetterPaths())->map(fn ($path, $index) => $booking->medicalReferralLetterUrl($panel ?? null, $index))->all()"
            btn-class="btn-outline-info py-0"
            label="دانلود"
        />
    </li>
    @endif
    @if($booking->isCredit() && $booking->hasCreditLetters())
    <li class="list-group-item d-flex justify-content-between align-items-start gap-2 px-0">
        <span class="text-muted">معرفی‌نامه اعتباری</span>
        <x-booking.authorized-document-links
            :urls="collect($booking->creditLetterPaths())->map(fn ($path, $index) => $booking->creditLetterUrl($panel ?? null, $index))->all()"
            btn-class="btn-outline-warning py-0"
            label="دانلود"
        />
    </li>
    @endif
    @php
        $bookingPricing = $pricingBreakdown ?? app(\App\Services\BookingReceiptBreakdownService::class)->pricingForBooking($booking);
        $bookingNaturalTotal = (int) ($bookingPricing['natural_total'] ?? $booking->total_price);
        $bookingManualAdjustment = (int) ($bookingPricing['manual_total_adjustment'] ?? 0);
    @endphp
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">مبلغ قابل پرداخت</span>
        <span class="text-end">
            <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($booking->total_price)) }} ریال</strong>
            @if($bookingManualAdjustment !== 0)
            <x-booking.manual-total-adjustment-note :adjustment="$bookingManualAdjustment" badge class="ms-1" />
            @endif
        </span>
    </li>
    @if($bookingManualAdjustment !== 0)
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">محاسبه خودکار</span>
        <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($bookingNaturalTotal)) }} ریال</span>
    </li>
    <li class="list-group-item d-flex justify-content-between gap-2 px-0 text-warning-emphasis">
        <span class="text-muted">تعدیل مبلغ رزرو</span>
        <span class="fw-semibold">{{ ($bookingManualAdjustment > 0 ? '+' : '−') . \App\Support\PdfPersian::toPersianDigits(number_format(abs($bookingManualAdjustment))) }} ریال</span>
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
@include('components.booking.show-details._stay-extension-form', ['booking' => $booking, 'canExtendStay' => $canExtendStay ?? false])
