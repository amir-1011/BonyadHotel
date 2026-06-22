<ul class="list-group list-group-flush">
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">گروه اعمال‌شده</span>
        <strong class="text-end">{{ $booking->veteranDiscountLabel() }}</strong>
    </li>
    @if($booking->veteran_type_applied)
    <li class="list-group-item d-flex justify-content-between gap-2 px-0">
        <span class="text-muted">تخفیف اقامت گروه</span>
        <span>{{ $booking->discount_percentage }}٪</span>
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
            <span class="text-muted small">({{ $g->relation }})</span>
            @endif
            <span class="badge text-bg-info ms-1">{{ $g->manual_discount_percentage }}٪</span>
            @if($g->manual_discount_reason)
            <div class="text-muted mt-1" style="font-size:.78rem">{{ $g->manual_discount_reason }}</div>
            @endif
        </div>
        @endforeach
    </li>
    @endif
    @if($excludedGuests->isEmpty() && $manualDiscountGuests->isEmpty() && !$booking->veteran_type_applied)
    <li class="list-group-item px-0 text-muted">تخفیف ایثارگری یا تخفیف دستی ثبت نشده است.</li>
    @endif
</ul>
