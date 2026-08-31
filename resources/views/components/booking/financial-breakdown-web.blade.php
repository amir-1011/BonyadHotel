{{--
    User-friendly financial breakdown for booking detail modal (web only).
--}}
@include('partials._booking-financial-styles')

@php
    $roomSubtotal = (int) ($pricing['room_subtotal'] ?? $booking->roomSubtotal());
    $extraGuestsTotal = (int) ($pricing['extra_guests_total'] ?? $booking->extra_guests_price);
    $childrenDiscount = (int) ($pricing['children_discount_amount'] ?? 0);
    $veteranAccDiscount = (int) ($pricing['veteran_accommodation_discount_amount'] ?? 0);
    $manualAccDiscount = (int) ($pricing['manual_accommodation_discount_amount'] ?? 0);
    $accBreakdown = $pricing['accommodation_discount_breakdown'] ?? [];
    $veteranNights = (int) ($pricing['veteran_discount_nights'] ?? 0);
    $totalNights = (int) ($pricing['nights'] ?? $booking->nights);
    $billingGuests = (int) ($pricing['billing_guests'] ?? $booking->billingGuests());
    $svcLines = $pricing['service_lines'] ?? [];
    $servicesSubtotal = (int) ($pricing['services_subtotal'] ?? $booking->services_subtotal);
    $subtotalBefore = (int) ($pricing['subtotal_before_discount'] ?? $booking->base_price);
    $totalDiscount = (int) ($pricing['discount_amount'] ?? $booking->discount_amount);
    $naturalTotal = (int) ($pricing['natural_total'] ?? $pricing['total_price'] ?? $booking->total_price);
    $manualAdjustment = (int) ($pricing['manual_total_adjustment'] ?? 0);
    $totalPrice = (int) ($pricing['payable_total'] ?? $booking->total_price);
    $platformCommission = (int) ($pricing['platform_commission_amount'] ?? 0);
    $childrenUnder6 = (int) ($pricing['children_under_6'] ?? $booking->children_under_6 ?? 0);

    $accGross = $roomSubtotal + $extraGuestsTotal;
    $accDiscountTotal = $childrenDiscount + $veteranAccDiscount + $manualAccDiscount;
    $servicesPayable = (int) $booking->services->sum(fn ($svc) => $svc->payableTotal());
    $serviceCount = $booking->services->count();

    $stayMeta = $totalNights . ' شب';
    if ($billingGuests > 1) {
        $stayMeta .= ' · ' . $billingGuests . ' تخت';
    }
    if ($booking->extra_guests > 0) {
        $stayMeta .= ' · ' . $booking->extra_guests . ' کف‌خواب';
    }
@endphp

<div class="bnb-fin">
    {{-- Hero: what guest actually pays --}}
    <section class="bnb-fin-hero">
        <div class="bnb-fin-hero__label">
            {{ $booking->isMedicalAccommodation() ? 'بدهی کارفرما (بیمه دی)' : 'مبلغ قابل پرداخت' }}
        </div>
        <div class="bnb-fin-hero__amount" dir="ltr">

            <span class="bnb-fin-currency">ریال</span>
        </div>
        @if($booking->isMedicalAccommodation())
        <div class="bnb-fin-hero__note">
            <span class="bnb-fin-pill">قابل پرداخت مهمان: ۰ ریال</span>
            @if($booking->medicalContractNumber())
            <span class="bnb-fin-hero__sub">قرارداد {{ $booking->medicalContractNumber() }}</span>
            @endif
            @if($booking->medicalTariffLabel())
            <span class="bnb-fin-hero__sub">تعرفه {{ $booking->medicalTariffLabel() }}</span>
            @endif
        </div>
        @endif
        @if($manualAdjustment !== 0)
        <div class="bnb-fin-hero__note">
            <span class="bnb-fin-pill bnb-fin-pill--warn">
                تعدیل مبلغ {{ ($manualAdjustment > 0 ? '+' : '−') . \App\Support\PdfPersian::toPersianDigits(number_format(abs($manualAdjustment))) }} ریال
            </span>
            <span class="bnb-fin-hero__sub">
                محاسبه سیستم: {{ \App\Support\PdfPersian::toPersianDigits(number_format($naturalTotal)) }} ریال
            </span>
        </div>
        @endif
    </section>

    {{-- Accommodation --}}
    <section class="bnb-fin-section">
        <header class="bnb-fin-section__head">
            <div class="bnb-fin-section__icon bnb-fin-section__icon--stay"><i class="bi bi-house-door"></i></div>
            <div>
                <h6 class="bnb-fin-section__title">اقامت</h6>
                <p class="bnb-fin-section__meta">{{ $stayMeta }}</p>
            </div>
        </header>
        <div class="bnb-fin-section__body">
            <x-booking.financial-row
                :label="!empty($pricing['is_medical_accommodation']) || $booking->isMedicalAccommodation() ? 'تعرفه اسکان درمانی' : 'کرایه اتاق'"
                :hint="$totalNights . ' شب' . ($billingGuests > 1 ? ' · ' . $billingGuests . ' تخت' : '')"
                :amount="$roomSubtotal"
            />
            @if($extraGuestsTotal > 0)
            <x-booking.financial-row
                :label="(!empty($pricing['is_medical_accommodation']) || $booking->isMedicalAccommodation()) ? 'همراه (تعرفه)' : 'کف‌خواب'"
                :hint="(!empty($pricing['is_medical_accommodation']) || $booking->isMedicalAccommodation())
                    ? (($pricing['medical']['billed_companions'] ?? $booking->medical_companion_count) . ' نفر')
                    : ($booking->extra_guests . ' نفر')"
                :amount="$extraGuestsTotal"
            />
            @endif

            @if($accDiscountTotal > 0)
            <div class="bnb-fin-subblock">
                <div class="bnb-fin-subblock__title">تخفیف‌های اقامت</div>

                @if($childrenDiscount > 0)
                <x-booking.financial-row
                    label="کودک زیر ۶ سال"
                    :hint="$childrenUnder6 . ' نفر'"
                    :amount="$childrenDiscount"
                    variant="discount"
                    sign="−"
                    compact
                />
                @endif

                @if($veteranAccDiscount > 0)
                    @if(!empty($accBreakdown))
                        @foreach($accBreakdown as $item)
                        @php
                            $groupLabel = trim((string) ($item['veteran_group_label'] ?? ''));
                            $itemAmount = (int) ($item['discount_amount'] ?? 0);
                            $tierHint = \App\Services\AccommodationDiscountTierEngine::tierBreakdownHint($item);
                            $rowHint = $veteranNights > 0 && $veteranNights < $totalNights
                                ? 'از ' . $totalNights . ' شب اقامت · ' . $tierHint
                                : $tierHint;
                        @endphp
                        <x-booking.financial-row
                            :label="$groupLabel !== '' ? 'تخفیف ایثارگری · ' . $groupLabel : 'تخفیف ایثارگری'"
                            :hint="$rowHint"
                            :amount="$itemAmount"
                            variant="discount"
                            sign="−"
                            compact
                        />
                        @endforeach
                    @else
                        @php
                            $veteranHint = $booking->veteranLabelApplied();
                            if ($veteranNights > 0 && $veteranNights < $totalNights) {
                                $veteranHint .= ' · ' . $veteranNights . ' شب از ' . $totalNights;
                            } elseif ($booking->discount_percentage) {
                                $veteranHint .= ' · ' . $booking->discount_percentage . '٪';
                            }
                        @endphp
                        <x-booking.financial-row
                            label="تخفیف ایثارگری"
                            :hint="$veteranHint"
                            :amount="$veteranAccDiscount"
                            variant="discount"
                            sign="−"
                            compact
                        />
                    @endif
                @endif

                @if($manualAccDiscount > 0)
                <x-booking.financial-row
                    label="تخفیف میزبان (اقامت)"
                    hint="مهمانان با نرخ عادی"
                    :amount="$manualAccDiscount"
                    variant="discount"
                    sign="−"
                    compact
                />
                @endif
            </div>
            @endif

            <x-booking.financial-row
                label="جمع بخش اقامت"
                :amount="max(0, $accGross - $accDiscountTotal)"
                variant="total"
            />
        </div>
    </section>

    {{-- Services --}}
    @if($serviceCount > 0)
    <section class="bnb-fin-section">
        <header class="bnb-fin-section__head">
            <div class="bnb-fin-section__icon bnb-fin-section__icon--svc"><i class="bi bi-bag-check"></i></div>
            <div>
                <h6 class="bnb-fin-section__title">خدمات</h6>
                <p class="bnb-fin-section__meta">
                    {{ $serviceCount }} مورد
                    @if($servicesSubtotal > 0)
                    · لیست {{ \App\Support\PdfPersian::toPersianDigits(number_format($servicesSubtotal)) }} ریال
                    @endif
                </p>
            </div>
            <div class="bnb-fin-section__aside" dir="ltr">
                {{ \App\Support\PdfPersian::toPersianDigits(number_format($servicesPayable)) }} <span class="bnb-fin-currency">ریال</span>
            </div>
        </header>
        <div class="bnb-fin-section__body bnb-fin-section__body--stack">
            @foreach($booking->services as $i => $svc)
            @php
                $line = $svcLines[$i] ?? null;
                $svcGuest = $svc->guest_sort_order !== null
                    ? $booking->guestDetails->firstWhere('sort_order', $svc->guest_sort_order)
                    : null;
                $roomLabel = $svcGuest ? $booking->guestPhysicalRoomLabel($svcGuest) : null;
            @endphp
            <x-booking.financial-service-card
                :service="$svc"
                :line="$line"
                :guest="$svcGuest"
                :room-label="$roomLabel"
            />
            @endforeach
        </div>
    </section>
    @endif

    {{-- Summary --}}
    <section class="bnb-fin-section bnb-fin-section--summary">
        <header class="bnb-fin-section__head">
            <div class="bnb-fin-section__icon bnb-fin-section__icon--sum"><i class="bi bi-calculator"></i></div>
            <div>
                <h6 class="bnb-fin-section__title">سرجمع</h6>
                <p class="bnb-fin-section__meta">خلاصه نهایی صورتحساب</p>
            </div>
        </header>
        <div class="bnb-fin-section__body">
            <x-booking.financial-row
                label="جمع قبل از تخفیف"
                :amount="$subtotalBefore"
                variant="muted"
            />
            @if($totalDiscount > 0)
            <x-booking.financial-row
                label="کل تخفیف‌ها"
                :amount="$totalDiscount"
                variant="discount"
                sign="−"
            />
            @endif
            @if($manualAdjustment !== 0)
            <x-booking.financial-row
                label="تعدیل مبلغ رزرو"
                :hint="'محاسبه سیستم ' . \App\Support\PdfPersian::toPersianDigits(number_format($naturalTotal)) . ' ریال'"
                :amount="abs($manualAdjustment)"
                variant="adjustment"
                :sign="$manualAdjustment > 0 ? '+' : '−'"
            />
            @endif
            @if($platformCommission > 0)
            <x-booking.financial-row
                label="کارمزد سامانه"
                :amount="$platformCommission"
                variant="muted"
            />
            @endif
            <x-booking.financial-row
                :label="$booking->isMedicalAccommodation() ? 'بدهی کارفرما (بیمه دی)' : 'مبلغ قابل پرداخت'"
                :amount="$booking->isMedicalAccommodation() ? ($booking->employerDebtAmount() ?: $totalPrice) : $totalPrice"
                variant="hero"
                class="bnb-fin-row--final"
            />
        </div>
    </section>
</div>
