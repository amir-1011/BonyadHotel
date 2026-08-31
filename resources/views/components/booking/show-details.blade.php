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

    $roomSummary = $booking->roomLinesSummary();

    $guestSummary = $displayGuestRows->isNotEmpty()
        ? $displayGuestRows->count() . ' نفر ثبت‌شده'
        : ($booking->guests . ' نفر');

    $pricingBreakdown = app(\App\Services\BookingReceiptBreakdownService::class)->pricingForBooking($booking);
    $accBreakdown = $pricingBreakdown['accommodation_discount_breakdown'] ?? [];
    $veteranAccDiscount = (int) ($pricingBreakdown['veteran_accommodation_discount_amount'] ?? 0);
    $manualTotalAdjustment = (int) ($pricingBreakdown['manual_total_adjustment'] ?? 0);
    $naturalTotalPrice = (int) ($pricingBreakdown['natural_total'] ?? $booking->total_price);
    $discountDetailSummary = $booking->veteranDiscountLabel();
    if (!$booking->billsAsRegularGuest() && count($accBreakdown) > 1) {
        $discountDetailSummary .= ' · ' . collect($accBreakdown)->map(
            fn ($row) => \App\Services\AccommodationDiscountTierEngine::tierBreakdownHint($row)
        )->join(' + ');
    } elseif (!$booking->billsAsRegularGuest() && $booking->veteran_type_applied && count($accBreakdown) === 1) {
        $discountDetailSummary .= ' · ' . \App\Services\AccommodationDiscountTierEngine::tierBreakdownHint($accBreakdown[0]);
    } elseif (!$booking->billsAsRegularGuest() && $booking->veteran_type_applied) {
        $discountDetailSummary .= ' · ' . $booking->discount_percentage . '٪ اقامت';
    }
    if ($manualDiscountGuests->isNotEmpty()) {
        $discountDetailSummary .= ' · ' . $manualDiscountGuests->count() . ' تخفیف دستی';
    }
    $discountSummary = $discountDetailSummary;

    $servicesCount = $booking->services->count();
    $beneficiaryCosts = $booking->beneficiaryCosts;
    $hasBeneficiaries = $beneficiaryCosts->isNotEmpty();
    $hasNotes = $booking->notes || $booking->form_file_path || $booking->hasMedicalReferralLetters() || $booking->hasCreditLetters();
    $bookingSummaryHtml = view('components.booking.show-details._snippets.booking-summary', ['booking' => $booking])->render();
    $allGuestSlots = $booking->allGuestSlotsForDisplay();
    if (!isset($canEditGuestNames)) {
        $canEditGuestNames = $booking->canEditGuestDetails(auth()->user())
            && (($panel ?? 'guest') !== 'host' || auth()->user()?->hostCan('bookings.guests', 'edit'));
    }
    if (!isset($canExtendStay)) {
        $canExtendStay = $booking->canExtendStay(auth()->user())
            && (($panel ?? 'guest') !== 'host' || auth()->user()?->hostCan('bookings.dates', 'edit'));
    }
    if (!isset($canModifyBookingRooms)) {
        $canModifyBookingRooms = $booking->canEditBookingDetails(auth()->user())
            && $booking->booking_source !== 'online'
            && !$booking->hasPendingCancellationRequest()
            && (($panel ?? 'guest') !== 'host' || auth()->user()?->hostCan('bookings.rooms', 'write'));
    }
@endphp

<div class="booking-show-details">

    {{-- Quick total banner --}}
    <div class="card shadow-sm border-primary border-opacity-25 mb-3">
        <div class="card-body py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div class="text-muted small mb-1">
                    {{ $booking->isMedicalAccommodation() ? 'بدهی کارفرما (بیمه دی)' : 'مبلغ قابل پرداخت' }}
                </div>
                <div class="fs-4 fw-bold text-primary">
                    {{ \App\Support\PdfPersian::toPersianDigits(number_format($booking->isMedicalAccommodation() ? $booking->employerDebtAmount() ?: $booking->total_price : $booking->total_price)) }}
                    <span class="fs-6 fw-normal">ریال</span>
                    @if($manualTotalAdjustment !== 0)
                    <x-booking.manual-total-adjustment-note :adjustment="$manualTotalAdjustment" badge class="ms-1 align-middle fs-6" />
                    @endif
                </div>
                @if($booking->isMedicalAccommodation())
                <div class="small text-muted mt-1">
                    قابل پرداخت مهمان: ۰ ریال
                    @if($booking->employer)
                        · کارفرما: {{ $booking->employer->name }}
                    @endif
                    @if($booking->medicalContractNumber())
                        · قرارداد: <span dir="ltr">{{ $booking->medicalContractNumber() }}</span>
                    @endif
                    @if($booking->medicalTariffLabel())
                        · تعرفه: {{ $booking->medicalTariffLabel() }}
                    @endif
                </div>
                @endif
                @if($manualTotalAdjustment !== 0)
                <div class="small text-muted mt-1">
                    محاسبه خودکار: {{ \App\Support\PdfPersian::toPersianDigits(number_format($naturalTotalPrice)) }} ریال
                </div>
                @endif
            </div>
            <div class="text-end small text-muted">
                <div class="d-flex align-items-center justify-content-end gap-2 flex-wrap">
                    <code dir="ltr">{{ $booking->tracking_code }}</code>
                    @if(!empty($pdfUrl))
                    <a href="{{ $pdfUrl }}" target="_blank" class="btn btn-sm btn-outline-success"><i class="bi bi-file-pdf me-1"></i>PDF</a>
                    @endif
                </div>
                <div class="mt-1">
                    <span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span>
                    @if($booking->isManual())
                    <span class="badge bg-info text-dark">دستی</span>
                    @endif
                    @if($booking->isMedicalAccommodation())
                    <span class="badge bg-info text-dark">اسکان درمانی</span>
                    @endif
                    @if($booking->isCredit())
                    <span class="badge bg-warning text-dark">اعتباری</span>
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
                'title' => 'مهمان اصلی',
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
            @php
                $perGuestServices = $booking->guestDetails->isNotEmpty();
                $servicesSummary = $servicesCount . ' خدمت · ' . \App\Support\PdfPersian::toPersianDigits(number_format($booking->services_subtotal)) . ' ریال';
                if ($perGuestServices) {
                    $servicesSummary .= ' · به‌ازای مهمان';
                }
            @endphp
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-services-' . $bid,
                'icon' => 'bag-plus',
                'title' => 'خدمات اضافی',
                'accent' => 'dark',
                'summary' => $servicesSummary,
            ])
        </div>
        @endif

        @if($hasBeneficiaries)
        <div class="col-sm-6 col-lg-4">
            @php
                $beneficiarySummary = $beneficiaryCosts->count() . ' ذینفع · ' . \App\Support\PdfPersian::toPersianDigits(number_format($beneficiaryCosts->sum('debt_amount'))) . ' ریال بدهی';
            @endphp
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-beneficiaries-' . $bid,
                'icon' => 'building',
                'title' => 'ذینفعان',
                'accent' => 'success',
                'summary' => e($beneficiarySummary),
            ])
        </div>
        @endif

        <div class="col-sm-6 col-lg-4">
            @php
                $financialSummary = 'جمع ' . \App\Support\PdfPersian::toPersianDigits(number_format($booking->base_price)) . ' ریال';
                if ($booking->discount_amount > 0) {
                    $financialSummary .= ' · تخفیف −' . \App\Support\PdfPersian::toPersianDigits(number_format($booking->discount_amount)) . ' ریال';
                }
                if ($manualTotalAdjustment !== 0) {
                    $signedManual = ($manualTotalAdjustment > 0 ? '+' : '−') . \App\Support\PdfPersian::toPersianDigits(number_format(abs($manualTotalAdjustment)));
                    $financialSummary .= ' · تعدیل ' . $signedManual . ' ریال';
                }
            @endphp
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-financial-' . $bid,
                'icon' => 'receipt',
                'title' => 'جزئیات مالی',
                'accent' => 'primary',
                'summary' => $financialSummary,
            ])
        </div>

        @if($hasNotes)
        <div class="col-sm-6 col-lg-4">
            @include('components.booking.show-details.summary-card', [
                'modalId' => 'bd-modal-notes-' . $bid,
                'icon' => 'journal-text',
                'title' => 'یادداشت و پیوست',
                'accent' => 'secondary',
                'summary' => $booking->notes
                    ? e(\Illuminate\Support\Str::limit($booking->notes, 60))
                    : ($booking->hasMedicalReferralLetters()
                        ? 'معرفی‌نامه اسکان درمانی'
                        : ($booking->hasCreditLetters() ? 'معرفی‌نامه اعتباری' : 'فرم امضا‌شده پیوست شده')),
            ])
        </div>
        @endif
    </div>

    <p class="text-muted text-center mt-3 mb-0" style="font-size:.78rem">
        <i class="bi bi-hand-index me-1"></i>برای مشاهده جزئیات کامل هر بخش، روی کارت مربوطه بزنید.
    </p>
</div>

{{-- ── Modals ── --}}
@php $modalVars = compact('booking', 'panel', 'roomLines', 'hasRoomLines', 'servicesDiscount', 'accommodationDiscount', 'manualDiscountGuests', 'excludedGuests', 'displayGuestRows', 'allGuestSlots', 'canEditGuestNames', 'canExtendStay', 'canModifyBookingRooms', 'bookerGuest', 'bookerManualDiscount', 'pricingBreakdown', 'accBreakdown', 'veteranAccDiscount'); @endphp

@include('components.booking.show-details.detail-modal-live', [
    'id' => 'bd-modal-booking-' . $bid,
    'title' => 'اطلاعات رزرو',
    'icon' => 'calendar-check',
    'size' => '',
    'bodyView' => 'components.booking.show-details._modal-booking',
    'bodyVars' => $modalVars,
])
@include('components.booking.show-details.detail-modal', [
    'id' => 'bd-modal-booker-' . $bid,
    'title' => 'مهمان اصلی',
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
@include('components.booking.show-details.detail-modal-live', [
    'id' => 'bd-modal-rooms-' . $bid,
    'title' => 'اتاق‌ها',
    'icon' => 'door-open',
    'size' => 'xl',
    'bodyView' => 'components.booking.show-details._modal-rooms',
    'bodyVars' => $modalVars,
])
@include('components.booking.show-details.detail-modal-live', [
    'id' => 'bd-modal-guests-' . $bid,
    'title' => 'مهمانان و تخفیف‌ها',
    'icon' => 'people',
    'size' => 'xl',
    'bodyView' => 'components.booking.show-details._modal-guests',
    'bodyVars' => $modalVars,
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
@if($hasBeneficiaries)
@include('components.booking.show-details.detail-modal', [
    'id' => 'bd-modal-beneficiaries-' . $bid,
    'title' => 'ذینفعان',
    'icon' => 'building',
    'size' => 'xl',
    'body' => view('components.booking.show-details._modal-beneficiaries', $modalVars)->render(),
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
