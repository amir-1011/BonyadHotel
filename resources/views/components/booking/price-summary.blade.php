@if(!empty($pricing))
<ul class="list-unstyled text-start d-inline-block mb-0">
    <li class="mb-1"><strong>اتاق:</strong> {{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['room_subtotal'] + $pricing['extra_guests_total'])) }} ریال</li>
    <li class="mb-1"><strong>خدمات:</strong> {{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['services_subtotal'])) }} ریال</li>
    <li class="mb-1"><strong>تخفیف اقامت:</strong> {{ $pricing['accommodation_discount_percentage'] ?? $discountPct }}٪
        @if($veteranType)
        ({{ $veteranGroups[$veteranType]['label'] ?? '' }})
        @endif
    </li>
    @if(($pricing['services_discount_amount'] ?? 0) > 0)
    <li class="mb-1"><strong>تخفیف خدمات:</strong> −{{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['services_discount_amount'])) }} ریال</li>
    @endif
    <li class="mb-1"><strong>جمع تخفیف:</strong> −{{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['discount_amount'])) }} ریال</li>
    @if(($pricing['platform_commission_amount'] ?? 0) > 0)
    <li class="mb-1"><strong>کارمزد سامانه:</strong> {{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['platform_commission_amount'])) }} ریال</li>
    @endif
    <li class="mb-1"><strong>پرداخت:</strong>
        @if($paymentMethod === 'cash') نقدی
        @elseif($paymentMethod === 'card_terminal') کارتخوان
        @elseif($paymentMethod === 'medical_accommodation') اسکان درمانی (بدهی بیمه دی — مهمان ۰ ریال)
        @elseif($paymentMethod === 'credit') اعتباری
        @else —
        @endif
    </li>
    <li class="fs-5 text-primary fw-bold mt-2">مبلغ نهایی: {{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['total_price'])) }} ریال</li>
</ul>
@endif
