@if($booking->services->isNotEmpty())
<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>خدمت</th>
                <th>قیمت واحد</th>
                <th>تعداد</th>
                <th>رایگان</th>
                <th>جمع</th>
                <th>تخفیف</th>
                <th>نهایی</th>
            </tr>
        </thead>
        <tbody>
            @foreach($booking->services as $svc)
            @php $lineSubtotal = $svc->unit_price * $svc->quantity; @endphp
            <tr>
                <td>
                    <strong>{{ $svc->name }}</strong>
                    @if($svc->serviceCatalog)
                    <div class="text-muted" style="font-size:.72rem">{{ $svc->serviceCatalog->name }}</div>
                    @endif
                </td>
                <td>{{ number_format($svc->unit_price) }} ت</td>
                <td>{{ $svc->quantity }}</td>
                <td>{{ ($svc->free_units ?? 0) > 0 ? $svc->free_units : '—' }}</td>
                <td>{{ number_format($lineSubtotal) }} ت</td>
                <td class="text-danger">
                    @if($svc->discount_amount > 0)
                        − {{ number_format($svc->discount_amount) }} ت
                        @if($svc->discount_percentage > 0)
                        <div class="text-muted" style="font-size:.72rem">{{ $svc->discount_percentage }}٪</div>
                        @endif
                    @else — @endif
                </td>
                <td class="fw-semibold">{{ number_format($svc->total) }} ت</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light">
            <tr>
                <td colspan="6" class="text-end text-muted">جمع خدمات</td>
                <td class="fw-semibold">{{ number_format($booking->services_subtotal) }} ت</td>
            </tr>
        </tfoot>
    </table>
</div>
@else
<p class="text-muted mb-0">خدمت اضافی ثبت نشده است.</p>
@endif
