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
    $totalPrice = (int) ($pricing['total_price'] ?? $booking->total_price);
    $groupUsage = $pricing['veteran_accommodation_group_usage'] ?? $booking->veteran_accommodation_group_usage ?? [];
    $fmt = $pdf
        ? fn (int $n) => \App\Support\PdfPersian::amount($n)
        : fn (int $n) => number_format($n) . ' تومان';
    $num = $pdf
        ? fn ($n) => \App\Support\PdfPersian::toPersianDigits((string) $n)
        : fn ($n) => $n;
@endphp

@if($pdf)
<table class="totals">
    <tr>
        <td>هزینه اقامت ({{ $num($totalNights) }} شب@if(($pricing['billing_guests'] ?? 0) > 1) · {{ $num($pricing['billing_guests']) }} تخت @endif)</td>
        <td class="amount">{{ $fmt($roomSubtotal) }}</td>
    </tr>
    @if($extraGuestsTotal > 0)
    <tr>
        <td>کف‌خواب ({{ $num($booking->extra_guests) }} نفر)</td>
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
        <td colspan="2">
            <table class="data" style="font-size:10px;margin-top:4px">
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
                                <div style="color:#dc2626;font-size:9px;margin-top:2px">
                                    تخفیف {{ $num($svc->discount_percentage) }}٪
                                    @if(($svc->free_units ?? 0) > 0)
                                        · {{ $num($svc->free_units) }} جلسه رایگان
                                    @endif
                                    (− {{ $fmt($svc->discount_amount) }})
                                </div>
                            @endif
                        </td>
                        <td>{{ $num($svc->quantity) }}</td>
                        <td class="amount">{{ $fmt($svc->unit_price) }}</td>
                        <td class="amount">{{ $fmt($lineSub) }}</td>
                        <td class="amount">{{ $fmt($svc->total) }}</td>
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
    <tr class="grand">
        <td>مبلغ قابل پرداخت</td>
        <td class="amount">{{ $fmt($totalPrice) }}</td>
    </tr>
</table>
@else
<div style="font-size:.85rem">
    <div class="d-flex justify-content-between py-1">
        <span class="text-muted">
            هزینه اقامت ({{ $totalNights }} شب
            @if(($pricing['billing_guests'] ?? 0) > 1) · {{ $pricing['billing_guests'] }} تخت @endif)
        </span>
        <span>{{ $fmt($roomSubtotal) }}</span>
    </div>
    @if($extraGuestsTotal > 0)
    <div class="d-flex justify-content-between py-1">
        <span class="text-muted">کف‌خواب ({{ $booking->extra_guests }} نفر)</span>
        <span>{{ $fmt($extraGuestsTotal) }}</span>
    </div>
    @endif
    @if($childrenDiscount > 0)
    <div class="d-flex justify-content-between py-1 text-success">
        <span class="text-muted">
            تخفیف کودک زیر ۶ سال
            ({{ $pricing['children_under_6'] ?? $booking->children_under_6 }} نفر)
        </span>
        <span>− {{ $fmt($childrenDiscount) }}</span>
    </div>
    @endif

    <div class="border-top mt-2 pt-2">
        @if($veteranAccDiscount > 0 || $manualAccDiscount > 0)
        <div class="text-muted small fw-semibold mb-1">تخفیف‌های اقامت</div>
        @endif

        @if($veteranAccDiscount > 0)
        <div class="py-1 text-danger">
            <div class="d-flex justify-content-between">
                <span>
                    تخفیف اقامت ایثارگری
                    @if($veteranNights > 0 && $veteranNights < $totalNights)
                    <br><span class="text-muted ms-3" style="font-size:.75rem">فقط {{ $veteranNights }} شب از {{ $totalNights }} شب</span>
                    @endif
                </span>
                @if(empty($accBreakdown))
                <span class="fw-semibold">− {{ $fmt($veteranAccDiscount) }}</span>
                @endif
            </div>
            <x-booking.accommodation-discount-breakdown
                :breakdown="$accBreakdown"
                :total="$veteranAccDiscount"
                compact
            />
        </div>
        @endif

        @if($manualAccDiscount > 0)
        <div class="d-flex justify-content-between py-1 text-danger">
            <span>تخفیف دستی اقامت (مهمانان نرخ عادی)</span>
            <span class="fw-semibold">− {{ $fmt($manualAccDiscount) }}</span>
        </div>
        @endif

        @if($servicesSubtotal > 0)
        <div class="d-flex justify-content-between py-1 mt-1">
            <span class="text-muted">خدمات اضافی (قبل از تخفیف)</span>
            <span>{{ $fmt($servicesSubtotal) }}</span>
        </div>
        @endif

        @if($booking->services->isNotEmpty())
        <div class="py-2 border-top mt-1">
            <div class="text-muted small mb-2 fw-semibold">جزئیات خدمات و تخفیف</div>
            @foreach($booking->services as $i => $svc)
            @php
                $line = $svcLines[$i] ?? null;
                $lineSub = $svc->unit_price * $svc->quantity;
            @endphp
            <div class="mb-2 ps-2 border-start border-secondary border-opacity-25">
                <div class="d-flex justify-content-between small">
                    <span class="fw-semibold">{{ $svc->name }}
                        <span class="text-muted fw-normal">({{ $svc->quantity }} × {{ number_format($svc->unit_price) }})</span>
                    </span>
                    <span>{{ number_format($lineSub) }} ت</span>
                </div>
                @if($line)
                <x-booking.service-discount-breakdown :line="$line" compact />
                <div class="d-flex justify-content-between small text-primary fw-semibold">
                    <span>مبلغ نهایی این خدمت</span>
                    <span>{{ number_format($svc->total) }} ت</span>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        <div class="border-top mt-2 pt-2">
            <div class="d-flex justify-content-between py-1 text-muted">
                <span>جمع قبل از تخفیف</span>
                <span>{{ $fmt($subtotalBefore) }}</span>
            </div>
            @if($totalDiscount > 0)
            <div class="d-flex justify-content-between py-1 text-danger">
                <span>مجموع تخفیفات</span>
                <span class="fw-semibold">− {{ $fmt($totalDiscount) }}</span>
            </div>
            @endif
            <div class="d-flex justify-content-between fw-bold pt-2 mt-1 border-top">
                <span>مبلغ قابل پرداخت</span>
                <span class="text-primary fs-5">{{ $fmt($totalPrice) }}</span>
            </div>
        </div>
    </div>
</div>
@endif
