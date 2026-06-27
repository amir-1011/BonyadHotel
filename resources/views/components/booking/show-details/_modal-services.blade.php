@php
    $servicePricingLines = $pricingBreakdown['service_lines'] ?? [];
@endphp
@if($booking->services->isNotEmpty())
<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>خدمت</th>
                <th>قیمت واحد</th>
                <th>تعداد</th>
                <th>جمع</th>
                <th>نهایی</th>
            </tr>
        </thead>
        <tbody>
            @foreach($booking->services as $i => $svc)
            @php
                $lineSubtotal = $svc->unit_price * $svc->quantity;
                $pricingLine = $servicePricingLines[$i] ?? null;
            @endphp
            <tr>
                <td>
                    <strong>{{ $svc->name }}</strong>
                    @if($svc->serviceCatalog)
                    <div class="text-muted" style="font-size:.72rem">{{ $svc->serviceCatalog->name }}</div>
                    @endif
                    @if($pricingLine)
                    <div class="mt-1">
                        <x-booking.service-discount-breakdown :line="$pricingLine" compact />
                    </div>
                    @elseif($svc->discount_amount > 0)
                    <div class="text-danger small mt-1">− {{ number_format($svc->discount_amount) }} ت</div>
                    @endif
                </td>
                <td>{{ number_format($svc->unit_price) }} ت</td>
                <td>{{ $svc->quantity }}</td>
                <td>{{ number_format($lineSubtotal) }} ت</td>
                <td class="fw-semibold">{{ number_format($svc->total) }} ت</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="4" class="text-end text-muted">جمع خدمات (قبل تخفیف)</td>
                <td class="fw-semibold">{{ number_format($booking->services_subtotal) }} ت</td>
            </tr>
        </tfoot>
    </table>
</div>
@else
<p class="text-muted mb-0">خدمت اضافی ثبت نشده است.</p>
@endif
