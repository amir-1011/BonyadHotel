<div style="font-size:.85rem">
    <div class="d-flex justify-content-between py-1">
        <span class="text-muted">هزینه اقامت ({{ $booking->nights }} شب)</span>
        <span>{{ number_format($booking->roomSubtotal()) }} تومان</span>
    </div>
    @if($booking->extra_guests_price > 0)
    <div class="d-flex justify-content-between py-1">
        <span class="text-muted">کف‌خواب ({{ $booking->extra_guests }} نفر)</span>
        <span>{{ number_format($booking->extra_guests_price) }} تومان</span>
    </div>
    @endif
    @if($booking->services_subtotal > 0)
    <div class="d-flex justify-content-between py-1">
        <span class="text-muted">خدمات اضافی</span>
        <span>{{ number_format($booking->services_subtotal) }} تومان</span>
    </div>
    @endif

    <div class="border-top mt-2 pt-2">
        <div class="d-flex justify-content-between py-1 text-muted">
            <span>جمع قبل از تخفیف</span>
            <span>{{ number_format($booking->base_price) }} تومان</span>
        </div>

        @if($accommodationDiscount > 0)
        <div class="d-flex justify-content-between py-1 text-danger">
            <span>
                تخفیف اقامت
                @if($booking->veteran_type_applied)
                    ({{ $booking->discount_percentage }}٪ · {{ $booking->veteranLabelApplied() }})
                @endif
            </span>
            <span class="fw-semibold">− {{ number_format($accommodationDiscount) }} تومان</span>
        </div>
        @endif

        @if($manualDiscountGuests->isNotEmpty())
        <div class="py-1 ps-3 border-start border-info border-2 ms-1 mb-1">
            <div class="text-muted small mb-1">تخفیف دستی برای:</div>
            @foreach($manualDiscountGuests as $g)
            <div class="small py-1">
                {{ $g->full_name }}
                @if($g->relation)<span class="text-muted">({{ $g->relation }})</span>@endif
                <span class="badge text-bg-info ms-1">{{ $g->manual_discount_percentage }}٪</span>
                @if($g->manual_discount_reason)
                <div class="text-muted" style="font-size:.72rem">{{ $g->manual_discount_reason }}</div>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        @if($servicesDiscount > 0)
        <div class="d-flex justify-content-between py-1 text-danger">
            <span>تخفیف خدمات</span>
            <span class="fw-semibold">− {{ number_format($servicesDiscount) }} تومان</span>
        </div>
        @endif

        @if($booking->discount_amount > 0)
        <div class="d-flex justify-content-between py-1 text-danger">
            <span>مجموع تخفیفات</span>
            <span class="fw-semibold">− {{ number_format($booking->discount_amount) }} تومان</span>
        </div>
        @endif

        <div class="d-flex justify-content-between fw-bold pt-2 mt-1 border-top">
            <span>مبلغ قابل پرداخت</span>
            <span class="text-primary fs-5">{{ number_format($booking->total_price) }} تومان</span>
        </div>
    </div>
</div>
