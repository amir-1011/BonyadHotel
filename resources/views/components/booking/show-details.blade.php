{{--
    Booking details — compact summary cards + detail modals.
    @param \App\Models\Booking $booking
    @param string $panel  admin|host
--}}
@php
    $bid = $booking->id;
    $roomLines = $booking->bookingRooms;
    $hasRoomLines = $roomLines->isNotEmpty();
    $servicesDiscount = $booking->servicesDiscountTotal();
    $accommodationDiscount = $booking->accommodationDiscountTotal();
    $manualDiscountGuests = $booking->manualDiscountSlotsForDisplay();
    $excludedGuests = $booking->excludedDiscountSlotsForDisplay();
    $displayGuestRows = $booking->guestRowsForDisplay();
    $bookerGuest = $booking->guestDetails->first();
    $bookerManualDiscount = $manualDiscountGuests->firstWhere('sort_order', 0);

    $roomSummary = $hasRoomLines
        ? $roomLines->count() . ' اتاق · ' . $roomLines->sum('guests') . ' نفر'
        : ($booking->roomType?->name ?? '—');

    $guestSummary = $displayGuestRows->isNotEmpty()
        ? $displayGuestRows->count() . ' نفر ثبت‌شده'
        : ($booking->guests . ' نفر');

    $discountSummary = $booking->veteran_type_applied
        ? $booking->veteranLabelApplied() . ' · ' . $booking->discount_percentage . '٪'
        : 'عادی';
    if ($manualDiscountGuests->isNotEmpty()) {
        $discountSummary .= ' · ' . $manualDiscountGuests->count() . ' تخفیف دستی';
    }

    $servicesCount = $booking->services->count();
    $hasNotes = $booking->notes || $booking->form_file_path;
    $bookingSummaryHtml = view('components.booking.show-details._snippets.booking-summary', ['booking' => $booking])->render();
@endphp

<div class="booking-show-details">

    {{-- Quick total banner --}}
    <div class="card shadow-sm border-primary border-opacity-25 mb-3">
        <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div class="text-muted small mb-1">مبلغ قابل پرداخت</div>
                <div class="fs-4 fw-bold text-primary">{{ number_format($booking->total_price) }} <span class="fs-6 fw-normal">تومان</span></div>
            </div>
            <div class="text-end small text-muted">
                <div><code dir="ltr">{{ $booking->tracking_code }}</code></div>
                <div class="mt-1">
                    <span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span>
                    @if($booking->isManual())
                    <span class="badge bg-info text-dark">دستی</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="row g-3">
        <div class="col-sm-6 col-lg-4">
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-booking-' . $bid,
                'icon' => 'calendar-check',
                'title' => 'اطلاعات رزرو',
                'accent' => 'primary',
                'summary' => $bookingSummaryHtml,
            ])
        </div>

        <div class="col-sm-6 col-lg-4">
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-booker-' . $bid,
                'icon' => 'person-badge',
                'title' => 'رزرو‌کننده',
                'accent' => 'secondary',
                'summary' => '<strong>' . e($booking->bookerName()) . '</strong><span dir="ltr" class="d-block">' . e($booking->bookerMobile()) . '</span>',
            ])
        </div>

        <div class="col-sm-6 col-lg-4">
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-discount-' . $bid,
                'icon' => 'shield-check',
                'title' => 'ایثارگری و تخفیف',
                'accent' => 'success',
                'summary' => e($discountSummary),
            ])
        </div>

        <div class="col-sm-6 col-lg-4">
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-rooms-' . $bid,
                'icon' => 'door-open',
                'title' => 'اتاق‌ها',
                'accent' => 'warning',
                'summary' => e($roomSummary),
            ])
        </div>

        <div class="col-sm-6 col-lg-4">
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-guests-' . $bid,
                'icon' => 'people',
                'title' => 'مهمانان',
                'accent' => 'info',
                'summary' => e($guestSummary) . ($manualDiscountGuests->isNotEmpty() ? ' <span class="badge text-bg-info ms-1">' . $manualDiscountGuests->count() . ' تخفیف دستی</span>' : ''),
            ])
        </div>

        @if($servicesCount > 0)
        <div class="col-sm-6 col-lg-4">
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-services-' . $bid,
                'icon' => 'bag-plus',
                'title' => 'خدمات اضافی',
                'accent' => 'dark',
                'summary' => $servicesCount . ' خدمت · ' . number_format($booking->services_subtotal) . ' تومان',
            ])
        </div>
        @endif

        <div class="col-sm-6 col-lg-4">
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-financial-' . $bid,
                'icon' => 'receipt',
                'title' => 'جزئیات مالی',
                'accent' => 'primary',
                'summary' => 'جمع ' . number_format($booking->base_price) . ' ت' . ($booking->discount_amount > 0 ? ' · تخفیف −' . number_format($booking->discount_amount) . ' ت' : ''),
            ])
        </div>

        @if($hasNotes)
        <div class="col-sm-6 col-lg-4">
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-notes-' . $bid,
                'icon' => 'journal-text',
                'title' => 'یادداشت و پیوست',
                'accent' => 'secondary',
                'summary' => $booking->notes ? e(\Illuminate\Support\Str::limit($booking->notes, 60)) : 'فرم امضا‌شده پیوست شده',
            ])
        </div>
        @endif
    </div>

    <p class="text-muted text-center mt-3 mb-0" style="font-size:.78rem">
        <i class="bi bi-hand-index me-1"></i>برای مشاهده جزئیات کامل هر بخش، روی کارت مربوطه بزنید.
    </p>
</div>

{{-- ── Modals ── --}}
@php $modalVars = compact('booking', 'panel', 'roomLines', 'hasRoomLines', 'servicesDiscount', 'accommodationDiscount', 'manualDiscountGuests', 'excludedGuests', 'displayGuestRows', 'bookerGuest', 'bookerManualDiscount'); @endphp

@include('components.booking.show-details.detail-modal', [
    'id' => 'bd-modal-booking-' . $bid,
    'title' => 'اطلاعات رزرو',
    'icon' => 'calendar-check',
    'size' => '',
    'body' => view('components.booking.show-details._modal-booking', $modalVars)->render(),
])
@include('components.booking.show-details.detail-modal', [
    'id' => 'bd-modal-booker-' . $bid,
    'title' => 'رزرو‌کننده',
    'icon' => 'person-badge',
    'size' => '',
    'body' => view('components.booking.show-details._modal-booker', $modalVars)->render(),
])
@include('components.booking.show-details.detail-modal', [
    'id' => 'bd-modal-discount-' . $bid,
    'title' => 'ایثارگری و تخفیف',
    'icon' => 'shield-check',
    'size' => '',
    'body' => view('components.booking.show-details._modal-discount', $modalVars)->render(),
])
@include('components.booking.show-details.detail-modal', [
    'id' => 'bd-modal-rooms-' . $bid,
    'title' => 'اتاق‌ها',
    'icon' => 'door-open',
    'size' => 'xl',
    'body' => view('components.booking.show-details._modal-rooms', $modalVars)->render(),
])
@include('components.booking.show-details.detail-modal', [
    'id' => 'bd-modal-guests-' . $bid,
    'title' => 'مهمانان و تخفیف‌ها',
    'icon' => 'people',
    'size' => 'xl',
    'body' => view('components.booking.show-details._modal-guests', $modalVars)->render(),
])
@if($servicesCount > 0)
@include('components.booking.show-details.detail-modal', [
    'id' => 'bd-modal-services-' . $bid,
    'title' => 'خدمات اضافی',
    'icon' => 'bag-plus',
    'size' => 'xl',
    'body' => view('components.booking.show-details._modal-services', $modalVars)->render(),
])
@endif
@include('components.booking.show-details.detail-modal', [
    'id' => 'bd-modal-financial-' . $bid,
    'title' => 'جزئیات مالی',
    'icon' => 'receipt',
    'size' => '',
    'body' => view('components.booking.show-details._modal-financial', $modalVars)->render(),
])
@if($hasNotes)
@include('components.booking.show-details.detail-modal', [
    'id' => 'bd-modal-notes-' . $bid,
    'title' => 'یادداشت و پیوست',
    'icon' => 'journal-text',
    'size' => '',
    'body' => view('components.booking.show-details._modal-notes', $modalVars)->render(),
])
@endif

<style>
.booking-detail-summary-card:hover {
    box-shadow: 0 .35rem 1rem rgba(0,0,0,.1) !important;
    transform: translateY(-2px);
}
.booking-detail-summary-card:focus {
    outline: 2px solid var(--bs-primary);
    outline-offset: 2px;
}
</style>
