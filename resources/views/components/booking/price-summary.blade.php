@if(!empty($pricing))
<ul class="list-unstyled text-start d-inline-block mb-0">
    <li class="mb-1"><strong>اتاق:</strong> {{ number_format($pricing['room_subtotal'] + $pricing['extra_guests_total']) }} تومان</li>
    <li class="mb-1"><strong>خدمات:</strong> {{ number_format($pricing['services_subtotal']) }} تومان</li>
    <li class="mb-1"><strong>تخفیف اقامت:</strong> {{ $pricing['accommodation_discount_percentage'] ?? $discountPct }}٪
        @if($veteranType)
        ({{ $veteranGroups[$veteranType]['label'] ?? '' }})
        @endif
    </li>
    @if(($pricing['services_discount_amount'] ?? 0) > 0)
    <li class="mb-1"><strong>تخفیف خدمات:</strong> −{{ number_format($pricing['services_discount_amount']) }} تومان</li>
    @endif
    <li class="mb-1"><strong>جمع تخفیف:</strong> −{{ number_format($pricing['discount_amount']) }} تومان</li>
    <li class="mb-1"><strong>پرداخت:</strong> {{ $paymentMethod === 'cash' ? 'نقدی' : 'کارتخوان' }}</li>
    <li class="fs-5 text-primary fw-bold mt-2">مبلغ نهایی: {{ number_format($pricing['total_price']) }} تومان</li>
</ul>
@endif
