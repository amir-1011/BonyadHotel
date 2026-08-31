<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: vazirmatn, dejavusans, sans-serif;
            font-size: 9px;
            color: #111;
            line-height: 1.35;
            direction: rtl;
            text-align: right;
            margin: 0;
        }

        h1 {
            font-size: 14px;
            font-weight: bold;
            margin: 0 0 2px;
        }

        .muted { color: #555; font-size: 8px; }
        .header { border-bottom: 1.5px solid #000; padding-bottom: 6px; margin-bottom: 6px; }
        .badge {
            display: inline-block;
            border: 1px solid #000;
            padding: 1px 4px;
            font-size: 7.5px;
            margin-left: 2px;
        }

        .section-title {
            font-size: 9.5px;
            font-weight: bold;
            margin: 5px 0 3px;
            padding: 2px 6px;
            background: #f3f4f6;
            border: 1px solid #000;
        }

        .info-card {
            border: 1px solid #000;
            margin-bottom: 5px;
            background: #fff;
        }

        .info-card-title {
            font-size: 8.5px;
            font-weight: bold;
            padding: 3px 6px;
            border-bottom: 1px solid #000;
            background: #fafafa;
        }

        table.kv {
            width: 100%;
            border-collapse: collapse;
        }

        table.kv td {
            padding: 2px 5px;
            vertical-align: top;
            border-bottom: 1px solid #ccc;
            font-size: 8.5px;
        }

        table.kv tr:last-child td { border-bottom: none; }
        table.kv td.lbl { width: 38%; color: #444; white-space: nowrap; border-left: 1px solid #ccc; }
        table.kv td.val { font-weight: bold; }

        table.grid {
            width: 100%;
            border-collapse: collapse;
        }

        table.grid td {
            vertical-align: top;
            width: 50%;
            padding: 0 0 0 4px;
        }

        table.grid td:first-child { padding: 0 4px 0 0; }

        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }

        table.data th,
        table.data td {
            border: 1px solid #000;
            padding: 2px 3px;
            text-align: right;
            vertical-align: top;
        }

        table.data th {
            background: #f3f4f6;
            font-weight: bold;
            font-size: 7.5px;
        }

        table.totals {
            width: 100%;
            margin-top: 4px;
            border-collapse: collapse;
            font-size: 8.5px;
        }

        table.totals td {
            padding: 2px 4px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }

        table.totals td.amount {
            text-align: left;
            direction: ltr;
            white-space: nowrap;
            width: 28%;
        }

        table.totals tr.grand td {
            font-size: 10px;
            font-weight: bold;
            border-top: 1.5px solid #000;
            border-bottom: none;
        }

        table.signatures {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }

        table.signatures td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding-top: 22px;
            border-top: 1px solid #666;
            font-size: 8.5px;
        }

        .note-box {
            border: 1px solid #ccc;
            padding: 3px 5px;
            font-size: 8px;
            background: #fafafa;
        }

        .discount-line { color: #b91c1c; font-size: 7.5px; margin-top: 1px; }
        .warn-line { color: #b45309; font-size: 7.5px; margin-top: 1px; }
        .ltr { direction: ltr; unicode-bidi: embed; }
        .nowrap { white-space: nowrap; }
    </style>
</head>
<body>
@php
    $pd = fn ($n) => \App\Support\PdfPersian::toPersianDigits((string) $n);
    $amt = fn (int $n) => \App\Support\PdfPersian::amount($n);
    $guestRows = $booking->allGuestSlotsForDisplay()->isNotEmpty()
        ? $booking->allGuestSlotsForDisplay()
        : $booking->guestRowsForDisplay();
    $manualDiscountGuests = $booking->manualDiscountSlotsForDisplay();
    $excludedGuests = $booking->excludedDiscountSlotsForDisplay();
    $accBreakdown = $pricing['accommodation_discount_breakdown'] ?? [];
    $accGroupUsage = $pricing['veteran_accommodation_group_usage'] ?? $booking->veteran_accommodation_group_usage ?? [];
    $veteranNights = (int) ($pricing['veteran_discount_nights'] ?? 0);
    $totalNights = (int) ($pricing['nights'] ?? $booking->nights);
    $serviceLines = $pricing['service_lines'] ?? [];
    $veteranAccDiscount = (int) ($pricing['veteran_accommodation_discount_amount'] ?? 0);
    $manualTotalAdjustment = (int) ($pricing['manual_total_adjustment'] ?? 0);
    $naturalTotal = (int) ($pricing['natural_total'] ?? $booking->total_price);
    $bookerGuest = $booking->bookerGuestDetail();
    $bookerManualDiscount = $manualDiscountGuests->firstWhere('sort_order', 0);
    $hasForeignGuests = $guestRows->contains(
        fn ($g) => $g instanceof \App\Models\BookingGuestDetail && $g->is_foreign_guest
    );
    $beneficiaryCosts = $booking->beneficiaryCosts;
    $hasNotes = $booking->notes
        || $booking->form_file_path
        || $booking->hasMedicalReferralLetters()
        || $booking->hasCreditLetters();
@endphp

    <div class="header">
        <table style="width:100%;border-collapse:collapse">
            <tr>
                <td>
                    <h1>فاکتور رزرو اقامتگاه</h1>
                    <div class="muted">
                        کد پیگیری: <strong class="ltr">{{ $booking->tracking_code }}</strong>
                        · تاریخ صدور: {{ $issuedAt }}
                        · تاریخ ثبت: {{ \App\Support\PdfPersian::jalali($booking->created_at, 'Y/m/d H:i') }}
                    </div>
                </td>
                <td style="text-align:left;vertical-align:top">
                    <span class="badge">{{ $booking->statusLabel() }}</span>
                    <span class="badge">{{ $booking->bookingSourceLabel() }}</span>
                    @if($booking->isManual())<span class="badge">دستی</span>@endif
                    @if($booking->isMedicalAccommodation())<span class="badge">اسکان درمانی</span>@endif
                    @if($booking->isCredit())<span class="badge">اعتباری</span>@endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ردیف ۱: اطلاعات رزرو + مهمان اصلی --}}
    <table class="grid">
        <tr>
            <td>
                <div class="section-title">اطلاعات رزرو</div>
                <div class="info-card">
                    <table class="kv">
                        <tr><td class="lbl">اقامتگاه</td><td class="val">{{ $booking->accommodation->name }}</td></tr>
                        <tr>
                            <td class="lbl">شهر / استان</td>
                            <td class="val">
                                {{ $booking->accommodation->city->name ?? '—' }}
                                @if($booking->accommodation->city?->province)
                                    · {{ $booking->accommodation->city->province->name }}
                                @endif
                            </td>
                        </tr>
                        <tr><td class="lbl">ورود</td><td class="val">{{ $checkInJalali }}</td></tr>
                        <tr><td class="lbl">خروج</td><td class="val">{{ $checkOutJalali }}</td></tr>
                        <tr><td class="lbl">مدت</td><td class="val">{{ $pd($booking->nights) }} شب</td></tr>
                        <tr>
                            <td class="lbl">مهمان</td>
                            <td class="val">
                                {{ $pd($booking->guests) }} نفر
                                @if(($booking->children_under_6 ?? 0) > 0)
                                    ({{ $pd($booking->children_under_6) }} کودک زیر ۶ سال)
                                @endif
                            </td>
                        </tr>
                        <tr><td class="lbl">تخت / اتاق</td><td class="val">{{ $pd($booking->billingGuests()) }} تخت · {{ $pd($booking->rooms_consumed) }} اتاق</td></tr>
                        @if($booking->extra_guests > 0)
                        <tr><td class="lbl">کف‌خواب</td><td class="val">{{ $pd($booking->extra_guests) }} نفر · {{ $amt($booking->extra_guests_price) }}</td></tr>
                        @endif
                        @if($booking->bill_full_rooms)
                        <tr><td class="lbl">رزرو کامل اتاق</td><td class="val">بله</td></tr>
                        @endif
                        <tr><td class="lbl">روش پرداخت</td><td class="val">{{ $paymentLabel }}</td></tr>
                        @if($booking->isMedicalAccommodation())
                        <tr><td class="lbl">قرارداد</td><td class="val ltr">{{ $booking->medicalContractNumber() ?: '—' }}</td></tr>
                        <tr><td class="lbl">تعرفه درمانی</td><td class="val">{{ $booking->medicalTariffLabel() ?: '—' }}</td></tr>
                        <tr><td class="lbl">کارفرما</td><td class="val">{{ $booking->employer?->name ?? 'بیمه دی' }}</td></tr>
                        @endif
                        <tr>
                            <td class="lbl">{{ $booking->isMedicalAccommodation() ? 'بدهی کارفرما' : 'مبلغ قابل پرداخت' }}</td>
                            <td class="val">
                                {{ $amt($booking->isMedicalAccommodation() ? ($booking->employerDebtAmount() ?: $booking->total_price) : $booking->total_price) }}
                                @if($manualTotalAdjustment !== 0)
                                    <div class="warn-line">تعدیل {{ ($manualTotalAdjustment > 0 ? '+' : '−') . ' ' . $amt(abs($manualTotalAdjustment)) }} · محاسبه: {{ $amt($naturalTotal) }}</div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
            <td>
                <div class="section-title">مهمان اصلی</div>
                <div class="info-card">
                    <table class="kv">
                        <tr><td class="lbl">نام</td><td class="val">{{ $booking->bookerName() }}</td></tr>
                        <tr><td class="lbl">موبایل</td><td class="val ltr">{{ $booking->bookerMobile() }}</td></tr>
                        @if($booking->bookerIdentityNumber())
                        <tr><td class="lbl">{{ $booking->bookerIdentityLabel() }}</td><td class="val ltr">{{ $booking->bookerIdentityNumber() }}</td></tr>
                        @endif
                        @if($booking->bookerResidenceLabel())
                        <tr><td class="lbl">محل اقامت</td><td class="val">{{ $booking->bookerResidenceLabel() }}</td></tr>
                        @endif
                        @if($bookerManualDiscount || ($bookerGuest && $bookerGuest->manual_discount_percentage))
                        @php $md = $bookerManualDiscount ?: $bookerGuest; @endphp
                        <tr>
                            <td class="lbl">تخفیف دستی</td>
                            <td class="val">
                                {{ $pd($md->manual_discount_percentage) }}٪
                                @if($md->manual_discount_reason)
                                    <div class="muted">{{ $md->manual_discount_reason }}</div>
                                @endif
                            </td>
                        </tr>
                        @endif
                    </table>
                </div>

                <div class="section-title">ایثارگری و تخفیف</div>
                <div class="info-card">
                    <table class="kv">
                        <tr><td class="lbl">گروه اعمال‌شده</td><td class="val">{{ $veteranLabel }}</td></tr>
                        @if($booking->isMedicalAccommodation())
                        <tr><td class="lbl">توضیح</td><td class="val" style="font-weight:normal;font-size:8px">اسکان درمانی · مهمان ۰ · بدهی کارفرما</td></tr>
                        @endif
                        @if($booking->isCredit())
                        <tr><td class="lbl">توضیح</td><td class="val" style="font-weight:normal;font-size:8px">اعتباری · بدون تخفیف ایثارگری</td></tr>
                        @endif
                        @if($booking->secondary_veteran_type_applied)
                        <tr>
                            <td class="lbl">گروه دوم</td>
                            <td class="val">{{ \App\Support\VeteranGroups::label($booking->secondary_veteran_type_applied, $booking->accommodation_id) }}</td>
                        </tr>
                        @endif
                        @if(!empty($accBreakdown))
                        <tr>
                            <td class="lbl">تخفیف اقامت</td>
                            <td class="val" style="font-weight:normal">
                                @foreach($accBreakdown as $item)
                                <div class="discount-line">{{ \App\Services\VeteranPolicyService::describeAccommodationBreakdownItem($item) }}</div>
                                @endforeach
                                @if($veteranAccDiscount > 0)
                                <div class="discount-line">جمع: − {{ $amt($veteranAccDiscount) }}</div>
                                @endif
                            </td>
                        </tr>
                        @elseif($booking->veteran_type_applied)
                        <tr><td class="lbl">تخفیف اقامت</td><td class="val">{{ $pd($booking->discount_percentage) }}٪</td></tr>
                        @endif
                        @if($veteranNights > 0)
                        <tr><td class="lbl">شب با تخفیف</td><td class="val">{{ $pd($veteranNights) }} از {{ $pd($totalNights) }} شب</td></tr>
                        @endif
                        @if(!empty($accGroupUsage))
                        <tr>
                            <td class="lbl">مصرف سقف</td>
                            <td class="val" style="font-weight:normal;font-size:8px">
                                @foreach($accGroupUsage as $gKey => $gNights)
                                    {{ \App\Support\VeteranGroups::label($gKey, $booking->accommodation_id) }}: {{ $pd($gNights) }} شب@if(!$loop->last) · @endif
                                @endforeach
                            </td>
                        </tr>
                        @endif
                        @if(($pricing['children_discount_amount'] ?? 0) > 0)
                        <tr><td class="lbl">تخفیف کودک</td><td class="val discount-line">− {{ $amt((int) $pricing['children_discount_amount']) }}</td></tr>
                        @endif
                        @if($excludedGuests->isNotEmpty())
                        <tr>
                            <td class="lbl">نرخ عادی</td>
                            <td class="val" style="font-weight:normal;font-size:8px">
                                @foreach($excludedGuests as $g)
                                    {{ $g->full_name ?: 'مهمان' }}@if(!$loop->last) · @endif
                                @endforeach
                            </td>
                        </tr>
                        @endif
                        @if($manualDiscountGuests->filter(fn ($g) => (int) $g->sort_order !== 0)->isNotEmpty())
                        <tr>
                            <td class="lbl">تخفیف دستی</td>
                            <td class="val" style="font-weight:normal;font-size:8px">
                                @foreach($manualDiscountGuests->filter(fn ($g) => (int) $g->sort_order !== 0) as $g)
                                    {{ $g->full_name ?: 'مهمان' }} {{ $pd($g->manual_discount_percentage) }}٪@if($g->manual_discount_reason) ({{ $g->manual_discount_reason }})@endif@if(!$loop->last) · @endif
                                @endforeach
                            </td>
                        </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- اتاق‌ها --}}
    @if($booking->bookingRooms->isNotEmpty() || $booking->roomType)
    <div class="section-title">اتاق‌ها</div>
    <div class="info-card" style="padding:0;border:none">
        @if($booking->bookingRooms->isNotEmpty())
        <table class="data">
            <thead>
                <tr>
                    <th>#</th>
                    <th>نوع</th>
                    <th>اختصاصی</th>
                    <th>تعرفه</th>
                    <th>بزرگسال</th>
                    <th>کودک&lt;۶</th>
                    <th>کف‌خواب</th>
                    <th>مصرف</th>
                </tr>
            </thead>
            <tbody>
                @foreach($booking->bookingRooms as $i => $line)
                <tr>
                    <td>{{ $pd($i + 1) }}</td>
                    <td>{{ $line->roomType?->name ?? '—' }}</td>
                    <td>{{ $line->room?->name ?? '—' }}</td>
                    <td>
                        {{ $line->roomRate?->name ?? '—' }}
                        @if($line->roomRate)
                        <div class="muted">{{ $amt($line->roomRate->price_per_night) }}/شب/تخت</div>
                        @endif
                    </td>
                    <td>{{ $pd($line->adults) }}</td>
                    <td>{{ $pd($line->children_under_6) }}</td>
                    <td>{{ $line->extra_guests ? $pd($line->extra_guests) : '—' }}</td>
                    <td>{{ $pd($line->rooms_consumed) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <table class="kv">
            <tr><td class="lbl">نوع اتاق</td><td class="val">{{ $booking->roomType->name }}</td></tr>
            @if($booking->roomRate)
            <tr><td class="lbl">تعرفه</td><td class="val">{{ $booking->roomRate->name }} · {{ $amt($booking->roomRate->price_per_night) }}/شب/تخت</td></tr>
            @endif
        </table>
        @endif
    </div>
    @endif

    {{-- مهمانان --}}
    @if($guestRows->isNotEmpty() || $booking->guests > 0)
    <div class="section-title">مهمانان</div>
    <div class="info-card" style="padding:0;border:none">
        <table class="data">
            <thead>
                <tr>
                    <th>#</th>
                    <th>نام</th>
                    <th>اتاق</th>
                    <th>{{ $hasForeignGuests ? 'کد/پاسپورت' : 'کد ملی' }}</th>
                    @if($hasForeignGuests)<th>اقامت</th>@endif
                    <th>موبایل</th>
                    <th>نسبت</th>
                    <th>تولد</th>
                    <th>تخفیف اقامت</th>
                    <th>تخفیف دستی</th>
                </tr>
            </thead>
            <tbody>
                @forelse($guestRows as $g)
                @php
                    $index = (int) ($g->sort_order ?? 0);
                    $roomLabel = $g instanceof \App\Models\BookingGuestDetail ? $booking->guestPhysicalRoomLabel($g) : null;
                    $displayName = trim((string) ($g->full_name ?? ''));
                    if ($displayName === '' || \App\Models\BookingGuestDetail::isGenericGuestName($displayName, $index)) {
                        $displayName = 'مهمان ' . ($index + 1);
                    }
                @endphp
                <tr>
                    <td>{{ $pd($index + 1) }}</td>
                    <td>
                        {{ $index === 0 ? ($g->full_name ?: $booking->bookerName()) : $displayName }}
                        @if($index === 0)<div class="muted">مهمان اصلی</div>@endif
                    </td>
                    <td>{{ $roomLabel ?: '—' }}</td>
                    <td class="ltr">{{ $g instanceof \App\Models\BookingGuestDetail ? ($g->identityNumber() ?: '—') : ($g->national_id ?? '—') }}</td>
                    @if($hasForeignGuests)
                    <td>{{ $g instanceof \App\Models\BookingGuestDetail && $g->is_foreign_guest ? ($g->residenceLocationLabel() ?: '—') : '—' }}</td>
                    @endif
                    <td class="ltr">{{ $g->mobile ?: '—' }}</td>
                    <td>{{ $g instanceof \App\Models\BookingGuestDetail ? $g->relationLabel() : '—' }}</td>
                    <td>{{ $g->birth_date ?? '—' }}</td>
                    <td>
                        @if($g->excluded_from_veteran_discount ?? false)
                            نرخ عادی
                        @elseif($booking->veteran_type_applied)
                            {{ $booking->veteranLabelApplied() }} · {{ $pd($booking->discount_percentage) }}٪
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($g->manual_discount_percentage ?? false)
                            {{ $pd($g->manual_discount_percentage) }}٪
                            @if($g->manual_discount_reason ?? null)
                            <div class="muted">{{ $g->manual_discount_reason }}</div>
                            @endif
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @empty
                @for($i = 0; $i < max(1, (int) $booking->guests); $i++)
                <tr>
                    <td>{{ $pd($i + 1) }}</td>
                    <td>{{ $i === 0 ? $booking->bookerName() : '' }}</td>
                    <td>—</td>
                    <td></td>
                    @if($hasForeignGuests)<td></td>@endif
                    <td class="ltr">{{ $i === 0 ? $booking->bookerMobile() : '' }}</td>
                    <td>—</td>
                    <td></td>
                    <td>—</td>
                    <td>—</td>
                </tr>
                @endfor
                @endforelse
            </tbody>
        </table>
    </div>
    @endif

    {{-- خدمات اضافی --}}
    @if($booking->services->isNotEmpty())
    <div class="section-title">خدمات اضافی</div>
    <div class="info-card" style="padding:0;border:none">
        <table class="data">
            <thead>
                <tr>
                    <th>خدمت</th>
                    <th>مهمان</th>
                    <th>تعداد</th>
                    <th>واحد</th>
                    <th>جمع</th>
                    <th>نهایی</th>
                </tr>
            </thead>
            <tbody>
                @foreach($booking->services as $i => $svc)
                @php
                    $line = $serviceLines[$i] ?? null;
                    $guestLabel = $svc->guest_sort_order !== null
                        ? ($booking->guestDetails->firstWhere('sort_order', $svc->guest_sort_order)?->full_name ?: 'مهمان ' . ($svc->guest_sort_order + 1))
                        : '—';
                    $usage = $line['veteran_group_usage'] ?? $svc->veteran_group_usage ?? [];
                @endphp
                <tr>
                    <td>
                        {{ $svc->name }}
                        @if(!empty($usage))
                        <div class="muted">
                            @foreach($usage as $gKey => $units)
                                {{ \App\Support\VeteranGroups::label($gKey, $booking->accommodation_id) }}: {{ $pd($units) }} جلسه@if(!$loop->last) · @endif
                            @endforeach
                        </div>
                        @endif
                        @if($line && !empty($line['discount_breakdown']))
                            @foreach($line['discount_breakdown'] as $item)
                            <div class="discount-line">{{ \App\Services\ServiceDiscountTierEngine::describeBreakdownItem($item) }}</div>
                            @endforeach
                        @elseif($svc->discount_amount > 0)
                            @php $reason = $svc->discountReasonLabel(); @endphp
                            <div class="discount-line">{{ $reason !== '' ? $reason : 'تخفیف' }} (− {{ $amt($svc->discount_amount) }})</div>
                        @endif
                        @if($svc->hasManualPriceAdjustment())
                        <div class="warn-line">تعدیل {{ ($svc->manualPriceAdjustmentAmount() > 0 ? '+' : '−') . ' ' . $amt(abs($svc->manualPriceAdjustmentAmount())) }}</div>
                        @endif
                    </td>
                    <td>{{ $guestLabel }}</td>
                    <td>{{ $pd($svc->quantity) }}</td>
                    <td class="ltr nowrap">{{ $amt($svc->unit_price) }}</td>
                    <td class="ltr nowrap">{{ $amt($svc->unit_price * $svc->quantity) }}</td>
                    <td class="ltr nowrap">{{ $amt($svc->payableTotal()) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding:3px 6px;font-size:8px;text-align:left">جمع خدمات: <strong>{{ $amt($booking->services_subtotal) }}</strong></div>
    </div>
    @endif

    {{-- ذینفعان --}}
    @if($beneficiaryCosts->isNotEmpty())
    <div class="section-title">ذینفعان</div>
    <div class="info-card" style="padding:0;border:none">
        <table class="data">
            <thead>
                <tr>
                    <th>ذینفع</th>
                    <th>شناسه</th>
                    <th>بدهی</th>
                    <th>کاربر</th>
                    <th>توضیح</th>
                </tr>
            </thead>
            <tbody>
                @foreach($beneficiaryCosts as $cost)
                @php $linkedUser = $cost->user ?? $cost->beneficiary?->user; @endphp
                <tr>
                    <td>{{ $cost->beneficiary?->name ?? '—' }}</td>
                    <td>{{ $cost->beneficiary?->beneficiary_code ?? '—' }}</td>
                    <td class="ltr nowrap">{{ $amt((int) $cost->debt_amount) }}</td>
                    <td>{{ $linkedUser?->name ?? $linkedUser?->mobile ?? '—' }}</td>
                    <td>{{ $cost->description ?: '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- جزئیات مالی --}}
    <div class="section-title">جزئیات مالی</div>
    <x-booking.financial-breakdown :booking="$booking" :pricing="$pricing" :pdf="true" />

    {{-- یادداشت و پیوست --}}
    @if($hasNotes)
    <div class="section-title">یادداشت و پیوست</div>
    <div class="info-card">
        @if($booking->notes)
        <div class="note-box" style="margin:4px">{{ $booking->notes }}</div>
        @endif
        <table class="kv">
            @if($booking->hasMedicalReferralLetters())
            <tr><td class="lbl">معرفی‌نامه درمانی</td><td class="val">{{ $pd(count($booking->medicalReferralLetterPaths())) }} فایل پیوست</td></tr>
            @endif
            @if($booking->hasCreditLetters())
            <tr><td class="lbl">معرفی‌نامه اعتباری</td><td class="val">{{ $pd(count($booking->creditLetterPaths())) }} فایل پیوست</td></tr>
            @endif
            @if($booking->form_file_path)
            <tr><td class="lbl">فرم امضا‌شده</td><td class="val">پیوست شده</td></tr>
            @endif
        </table>
    </div>
    @endif

    <table class="signatures">
        <tr>
            <td>امضای مهمان</td>
            <td>مهر و امضای پذیرش</td>
        </tr>
    </table>
</body>
</html>
