{{--
    Full pricing / discount breakdown for booking receipt (web or PDF).
    @param \App\Models\Booking $booking
    @param array<string,mixed> $pricing  from BookingReceiptBreakdownService::forBooking()
    @param bool $pdf
--}}
@props(['booking', 'pricing', 'pdf' => false])

@php
    $roomSubtotal = (int) ($pricing['room_subtotal'] ?? $booking->roomSubtotal());
    $extraGuestsTotal = (int) ($pricing['extra_guests_total'] ?? $booking->extra_guests_price);
    $childrenDiscount = (int) ($pricing['children_discount_amount'] ?? 0);
    $veteranAccDiscount = (int) ($pricing['veteran_accommodation_discount_amount'] ?? 0);
    $manualAccDiscount = (int) ($pricing['manual_accommodation_discount_amount'] ?? 0);
    $accBreakdown = $pricing['accommodation_discount_breakdown'] ?? [];
    $veteranNights = (int) ($pricing['veteran_discount_nights'] ?? 0);
    $totalNights = (int) ($pricing['nights'] ?? $booking->nights);
    $svcLines = $pricing['service_lines'] ?? [];
    $servicesSubtotal = (int) ($pricing['services_subtotal'] ?? $booking->services_subtotal);
    $subtotalBefore = (int) ($pricing['subtotal_before_discount'] ?? $booking->base_price);
    $totalDiscount = (int) ($pricing['discount_amount'] ?? $booking->discount_amount);
    $naturalTotal = (int) ($pricing['natural_total'] ?? $pricing['total_price'] ?? $booking->total_price);
    $manualAdjustment = (int) ($pricing['manual_total_adjustment'] ?? 0);
    $totalPrice = (int) ($pricing['payable_total'] ?? $booking->total_price);
    $platformCommission = (int) ($pricing['platform_commission_amount'] ?? 0);
    $groupUsage = $pricing['veteran_accommodation_group_usage'] ?? $booking->veteran_accommodation_group_usage ?? [];
    $fmt = $pdf
        ? fn (int $n) => \App\Support\PdfPersian::amount($n)
        : fn (int $n) => \App\Support\PdfPersian::toPersianDigits(number_format($n)) . ' ریال';
    $num = $pdf
        ? fn ($n) => \App\Support\PdfPersian::toPersianDigits((string) $n)
        : fn ($n) => $n;
    $isMedical = $booking->isMedicalAccommodation();
    $stayLabel = $isMedical ? 'تعرفه اسکان درمانی' : 'هزینه اقامت';
    $billingGuests = (int) ($pricing['billing_guests'] ?? 0);
    $stayMeta = $num($totalNights) . ' شب';
    if ($billingGuests > 1) {
        $stayMeta .= ' · ' . $num($billingGuests) . ' تخت';
    }
    $extraLabel = $isMedical ? 'همراه (تعرفه)' : 'کف‌خواب';
    $extraCount = $isMedical
        ? (int) ($pricing['medical']['billed_companions'] ?? $booking->medical_companion_count)
        : (int) $booking->extra_guests;
    $payableLabel = $isMedical ? 'بدهی کارفرما (بیمه دی) — قابل پرداخت مهمان: ۰' : 'مبلغ قابل پرداخت';
    $payableAmount = $isMedical ? ($booking->employerDebtAmount() ?: $totalPrice) : $totalPrice;
@endphp

@if($pdf)
<table class="totals">
    <tr>
        <td>{{ $stayLabel }} ({{ $stayMeta }})</td>
        <td class="amount">{{ $fmt($roomSubtotal) }}</td>
    </tr>
    @if($isMedical && $booking->medicalContractNumber())
    <tr>
        <td>شماره قرارداد</td>
        <td class="amount ltr">{{ $booking->medicalContractNumber() }}</td>
    </tr>
    @endif
    @if($extraGuestsTotal > 0)
    <tr>
        <td>{{ $extraLabel }} ({{ $num($extraCount) }} نفر)</td>
        <td class="amount">{{ $fmt($extraGuestsTotal) }}</td>
    </tr>
    @endif
    @if($childrenDiscount > 0)
    <tr>
        <td>
            تخفیف کودک زیر ۶ سال
            ({{ $num($pricing['children_under_6'] ?? $booking->children_under_6) }} نفر)
        </td>
        <td class="amount" style="color:#dc2626">− {{ $fmt($childrenDiscount) }}</td>
    </tr>
    @endif
    @if($veteranAccDiscount > 0)
    <tr>
        <td colspan="2" style="padding-top:6px">
            <strong>تخفیف اقامت ایثارگری</strong>
            @if($veteranNights > 0 && $veteranNights < $totalNights)
            <span style="font-size:10px;color:#6b7280"> — {{ $num($veteranNights) }} شب از {{ $num($totalNights) }} شب</span>
            @endif
            @if(!empty($accBreakdown))
                @foreach($accBreakdown as $item)
                <div style="color:#dc2626;font-size:10px;margin-top:3px">
                    {{ \App\Services\VeteranPolicyService::describeAccommodationBreakdownItem($item) }}
                </div>
                @endforeach
            @elseif($booking->veteran_type_applied)
                <div style="color:#dc2626;font-size:10px;margin-top:3px">
                    {{ $booking->veteranLabelApplied() }} · {{ $num($booking->discount_percentage) }}٪
                </div>
            @endif
        </td>
    </tr>
    <tr>
        <td>جمع تخفیف اقامت ایثارگری</td>
        <td class="amount" style="color:#dc2626">− {{ $fmt($veteranAccDiscount) }}</td>
    </tr>
    @endif
    @if($manualAccDiscount > 0)
    <tr>
        <td>تخفیف دستی اقامت (مهمانان نرخ عادی)</td>
        <td class="amount" style="color:#dc2626">− {{ $fmt($manualAccDiscount) }}</td>
    </tr>
    @endif
    @if($servicesSubtotal > 0)
    <tr>
        <td>خدمات اضافی (قبل از تخفیف)</td>
        <td class="amount">{{ $fmt($servicesSubtotal) }}</td>
    </tr>
    @endif
    @if($booking->services->isNotEmpty())
    <tr>
        <td colspan="2" style="padding:2px 0">
            <table class="data" style="font-size:7.5px;margin:0">
                <thead>
                    <tr>
                        <th>خدمت</th>
                        <th>تعداد</th>
                        <th>قیمت واحد</th>
                        <th>جمع</th>
                        <th>نهایی</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($booking->services as $i => $svc)
                    @php
                        $line = $svcLines[$i] ?? null;
                        $lineSub = $svc->unit_price * $svc->quantity;
                    @endphp
                    <tr>
                        <td>
                            {{ $svc->name }}
                            @if($line && !empty($line['discount_breakdown']))
                                @foreach($line['discount_breakdown'] as $item)
                                <div style="color:#dc2626;font-size:9px;margin-top:2px">
                                    {{ \App\Services\ServiceDiscountTierEngine::describeBreakdownItem($item) }}
                                </div>
                                @endforeach
                            @elseif($svc->discount_amount > 0)
                                @php $svcDiscountReason = $svc->discountReasonLabel(); @endphp
                                <div style="color:#dc2626;font-size:9px;margin-top:2px">
                                    {{ $svcDiscountReason !== '' ? $svcDiscountReason : 'تخفیف' }}
                                    (− {{ $fmt($svc->discount_amount) }})
                                </div>
                            @endif
                            @if($svc->hasManualPriceAdjustment())
                                <div style="color:#d97706;font-size:9px;margin-top:2px">
                                    تعدیل مبلغ {{ ($svc->manualPriceAdjustmentAmount() > 0 ? '+' : '−') . ' ' . $fmt(abs($svc->manualPriceAdjustmentAmount())) }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $num($svc->quantity) }}</td>
                        <td class="amount">{{ $fmt($svc->unit_price) }}</td>
                        <td class="amount">{{ $fmt($lineSub) }}</td>
                        <td class="amount">{{ $fmt($svc->payableTotal()) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>
    @endif
    <tr>
        <td>جمع قبل از تخفیف</td>
        <td class="amount">{{ $fmt($subtotalBefore) }}</td>
    </tr>
    @if($totalDiscount > 0)
    <tr>
        <td>مجموع تخفیفات</td>
        <td class="amount" style="color:#dc2626">− {{ $fmt($totalDiscount) }}</td>
    </tr>
    @endif
    @if($manualAdjustment !== 0)
    <tr>
        <td>تعدیل مبلغ رزرو (محاسبه خودکار: {{ $fmt($naturalTotal) }})</td>
        <td class="amount" style="color:#d97706">{{ ($manualAdjustment > 0 ? '+' : '−') . ' ' . $fmt(abs($manualAdjustment)) }}</td>
    </tr>
    @endif
    @if($platformCommission > 0)
    <tr>
        <td>کارمزد سامانه</td>
        <td class="amount">{{ $fmt($platformCommission) }}</td>
    </tr>
    @endif
    <tr class="grand">
        <td>{{ $payableLabel }}</td>
        <td class="amount">{{ $fmt($payableAmount) }}</td>
    </tr>
</table>
@else
@include('components.booking.financial-breakdown-web', compact('booking', 'pricing'))
@endif
