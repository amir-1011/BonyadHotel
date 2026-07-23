@php
    $veteranTypes = $booking->veteranTypesApplied();
    $accGroupUsage = $pricingBreakdown['veteran_accommodation_group_usage'] ?? $booking->veteran_accommodation_group_usage ?? [];
    $veteranNights = (int) ($pricingBreakdown['veteran_discount_nights'] ?? 0);
    $totalNights = (int) ($pricingBreakdown['nights'] ?? $booking->nights);
    $serviceLines = $pricingBreakdown['service_lines'] ?? [];
@endphp
<ul class="list-group list-group-flush">
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">گروه(های) اعمال‌شده</span>
        <strong class="text-end">{{ $booking->veteranDiscountLabel() }}</strong>
    </li>

    @if($booking->secondary_veteran_type_applied)
    <li class="list-group-item px-0">
        <div class="small text-muted mb-1">ترکیب گروه‌ها (اولویت تخفیف بیشتر)</div>
        <div class="d-flex flex-wrap gap-1">
            <span class="badge text-bg-success">{{ \App\Support\VeteranGroups::label($booking->veteran_type_applied, $booking->accommodation_id) }}</span>
            <span class="badge text-bg-primary">{{ \App\Support\VeteranGroups::label($booking->secondary_veteran_type_applied, $booking->accommodation_id) }}</span>
        </div>
    </li>
    @endif

    @if(!empty($accBreakdown))
    <li class="list-group-item px-0">
        <span class="text-muted d-block mb-2">تخفیف اقامت به تفکیک گروه</span>
        <x-booking.accommodation-discount-breakdown
            :breakdown="$accBreakdown"
            :total="$veteranAccDiscount"
            compact
        />
    </li>
    @elseif($booking->veteran_type_applied)
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">تخفیف اقامت گروه</span>
        <span>{{ $booking->discount_percentage }}٪</span>
    </li>
    @endif

    @if($veteranNights > 0)
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">شب‌های با تخفیف ایثارگری</span>
        <span>{{ $veteranNights }} از {{ $totalNights }} شب</span>
    </li>
    @endif

    @if(!empty($accGroupUsage))
    <li class="list-group-item px-0">
        <span class="text-muted d-block mb-2">مصرف سقف اقامت در این رزرو</span>
        @foreach($accGroupUsage as $gKey => $gNights)
        <div class="small py-1">
            <span class="fw-semibold">{{ \App\Support\VeteranGroups::label($gKey, $booking->accommodation_id) }}</span>
            <span class="text-muted"> — </span>
            <span>{{ $gNights }} شب</span>
        </div>
        @endforeach
    </li>
    @endif

    @if($booking->services->isNotEmpty())
    <li class="list-group-item px-0 border-top mt-1 pt-2">
        <span class="text-muted d-block mb-2">مصرف سقف خدمات در این رزرو</span>
        @foreach($booking->services as $i => $svc)
        @php
            $line = $serviceLines[$i] ?? null;
            $usage = $line['veteran_group_usage'] ?? $svc->veteran_group_usage ?? [];
        @endphp
        @if(!empty($usage))
        <div class="mb-2">
            <div class="small fw-semibold">{{ $svc->name }}</div>
            @foreach($usage as $gKey => $units)
            <div class="small text-muted ps-2">
                {{ \App\Support\VeteranGroups::label($gKey, $booking->accommodation_id) }}: {{ $units }} جلسه
            </div>
            @endforeach
            @if($line && !empty($line['discount_breakdown']))
            <div class="mt-1 ps-2">
                <x-booking.service-discount-breakdown :line="$line" compact />
            </div>
            @endif
        </div>
        @endif
        @endforeach
    </li>
    @endif

    @if($excludedGuests->isNotEmpty())
    <li class="list-group-item px-0">
        <span class="text-muted d-block mb-2">مهمانان با نرخ عادی (بدون تخفیف ایثارگری)</span>
        @foreach($excludedGuests as $g)
        <div class="border rounded px-2 py-1 mb-1 bg-light">
            <span class="badge text-bg-warning me-1">{{ $g->full_name ?: 'مهمان' }}</span>
            @if($g->manual_discount_percentage)
            <span class="badge text-bg-info">{{ $g->manual_discount_percentage }}٪ دستی</span>
            @endif
            @if($g->manual_discount_reason)
            <div class="text-muted mt-1" style="font-size:.75rem">{{ $g->manual_discount_reason }}</div>
            @endif
        </div>
        @endforeach
    </li>
    @endif

    @if($manualDiscountGuests->isNotEmpty())
    <li class="list-group-item px-0">
        <span class="text-muted d-block mb-2">تخفیف‌های دستی اقامت</span>
        @foreach($manualDiscountGuests as $g)
        <div class="border rounded px-2 py-1 mb-1 bg-light">
            <span class="fw-semibold">{{ $g->full_name ?: 'مهمان' }}</span>
            @if(!empty($g->from_snapshot))
            <span class="badge text-bg-secondary ms-1" style="font-size:.65rem">بدون مشخصات تماس</span>
            @endif
            @if($g->relation)
            <span class="text-muted small">({{ \App\Models\BookingGuestDetail::formatRelationLabel($g->relation) }})</span>
            @endif
            <span class="badge text-bg-info ms-1">{{ $g->manual_discount_percentage }}٪</span>
            @if($g->manual_discount_reason)
            <div class="text-muted mt-1" style="font-size:.78rem">{{ $g->manual_discount_reason }}</div>
            @endif
        </div>
        @endforeach
    </li>
    @endif

    @if(($pricingBreakdown['children_discount_amount'] ?? 0) > 0)
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">تخفیف کودک زیر ۶ سال</span>
        <span class="text-success">− {{ number_format($pricingBreakdown['children_discount_amount']) }} ت</span>
    </li>
    @endif

    @if($excludedGuests->isEmpty() && $manualDiscountGuests->isEmpty() && !$booking->veteran_type_applied)
    <li class="list-group-item px-0 text-muted">تخفیف ایثارگری یا تخفیف دستی ثبت نشده است.</li>
    @endif
</ul>
