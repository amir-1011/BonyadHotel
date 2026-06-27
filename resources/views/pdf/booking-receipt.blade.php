<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: vazirmatn, dejavusans, sans-serif;
            font-size: 12px;
            color: #1f2937;
            line-height: 1.7;
            direction: rtl;
            text-align: right;
        }

        h1 {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 4px;
            color: #111827;
        }

        .muted { color: #6b7280; font-size: 11px; }
        .header { border-bottom: 2px solid #e5e7eb; padding-bottom: 12px; margin-bottom: 16px; }

        .section-title-bw {
            font-size: 13px;
            font-weight: bold;
            margin: 0 0 8px;
            color: #000;
        }

        .info-card {
            border: 1px solid #000;
            margin-bottom: 16px;
            background: #fff;
        }

        .info-card-title {
            font-size: 12px;
            font-weight: bold;
            padding: 8px 12px;
            border-bottom: 1px solid #000;
            color: #000;
            background: #fff;
        }

        table.info-card-table {
            width: 100%;
            border-collapse: collapse;
        }

        table.info-card-table td {
            padding: 7px 12px;
            vertical-align: top;
            text-align: right;
            color: #000;
            border-bottom: 1px solid #000;
        }

        table.info-card-table tr:last-child td {
            border-bottom: none;
        }

        table.info-card-table td.label {
            width: 32%;
            font-weight: normal;
            white-space: nowrap;
            border-left: 1px solid #000;
        }

        table.info-card-table td.value {
            font-weight: bold;
        }

        table.info-card-table.guest-table td {
            border-left: 1px solid #000;
        }

        table.info-card-table.guest-table td:first-child {
            border-left: none;
        }

        table.info-card-table.guest-table tr.header-row td {
            font-weight: bold;
            font-size: 11px;
        }

        table.info-card-table.guest-table tr.data-row td {
            font-weight: normal;
            min-height: 32px;
            height: 32px;
        }

        table.info-card-table.guest-table tr.data-row td.writable {
            min-height: 36px;
            height: 36px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
        }

        table.data th,
        table.data td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: right;
        }

        table.data th {
            background: #f9fafb;
            font-weight: bold;
        }

        table.totals {
            width: 100%;
            margin-top: 12px;
            border-collapse: collapse;
        }

        table.totals td {
            padding: 5px 8px;
            text-align: right;
        }

        table.totals td.amount {
            text-align: left;
            direction: ltr;
            white-space: nowrap;
        }

        table.totals tr.grand td {
            font-size: 15px;
            font-weight: bold;
            color: #15803d;
            border-top: 2px solid #111;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            margin: 18px 0 8px;
            color: #374151;
            border-right: 3px solid #16a34a;
            padding-right: 8px;
        }

        table.signatures {
            width: 100%;
            margin-top: 48px;
            border-collapse: collapse;
        }

        table.signatures td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding-top: 48px;
            border-top: 1px solid #9ca3af;
        }

        .ltr { direction: ltr; unicode-bidi: embed; }
    </style>
</head>
<body>
    <div class="header">
        <h1>فیش رزرو اقامتگاه</h1>
        <div class="muted">
            کد پیگیری: <strong class="ltr">{{ $booking->tracking_code }}</strong>
            · تاریخ صدور: {{ $issuedAt }}
        </div>
    </div>

    <div class="section-title-bw">مشخصات رزرو</div>
    <div class="info-card">
        <div class="info-card-title">اطلاعات اقامت</div>
        <table class="info-card-table">
            <tr>
                <td class="label">اقامتگاه</td>
                <td class="value">{{ $booking->accommodation->name }}</td>
            </tr>
            <tr>
                <td class="label">شهر</td>
                <td class="value">
                    {{ $booking->accommodation->city->name ?? '—' }}
                    @if($booking->accommodation->city?->province)
                        · {{ $booking->accommodation->city->province->name }}
                    @endif
                </td>
            </tr>
            @if($booking->bookingRooms->isNotEmpty())
            <tr>
                <td class="label">اتاق‌های رزرو</td>
                <td class="value">
                    <table class="data" style="font-size:10px;margin:0">
                        <thead>
                            <tr>
                                <th>نوع</th>
                                <th>اتاق اختصاصی</th>
                                <th>تعرفه</th>
                                <th>نفر</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($booking->bookingRooms as $line)
                            <tr>
                                <td>{{ $line->roomType?->name ?? '—' }}</td>
                                <td>{{ $line->room?->name ?? '—' }}</td>
                                <td>{{ $line->roomRate?->name ?? '—' }}</td>
                                <td>{{ \App\Support\PdfPersian::toPersianDigits((string) $line->guests) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
            </tr>
            @elseif($booking->roomType)
            <tr>
                <td class="label">نوع اتاق</td>
                <td class="value">
                    {{ $booking->roomType->name }}
                    @if($booking->roomRate) — {{ $booking->roomRate->name }} @endif
                </td>
            </tr>
            @endif
            <tr>
                <td class="label">تاریخ ورود</td>
                <td class="value">{{ $checkInJalali }}</td>
            </tr>
            <tr>
                <td class="label">تاریخ خروج</td>
                <td class="value">{{ $checkOutJalali }}</td>
            </tr>
            <tr>
                <td class="label">تعداد شب</td>
                <td class="value">{{ \App\Support\PdfPersian::toPersianDigits((string) $booking->nights) }} شب</td>
            </tr>
            <tr>
                <td class="label">تعداد مهمان</td>
                <td class="value">
                    {{ \App\Support\PdfPersian::toPersianDigits((string) $booking->guests) }} نفر
                    @if($booking->extra_guests > 0)
                        (+ {{ \App\Support\PdfPersian::toPersianDigits((string) $booking->extra_guests) }} کف‌خواب)
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="info-card">
        <div class="info-card-title">اطلاعات رزرو‌کننده و پرداخت</div>
        <table class="info-card-table">
            <tr>
                <td class="label">رزرو‌کننده</td>
                <td class="value">
                    {{ $booking->guest_contact_name ?? $booking->user->name }}
                    — <span class="ltr">{{ $booking->guest_contact_mobile ?? $booking->user->mobile }}</span>
                </td>
            </tr>
            <tr>
                <td class="label">نوع رزرو</td>
                <td class="value">{{ $booking->isManual() ? 'دستی (پذیرش)' : 'آنلاین' }}</td>
            </tr>
            <tr>
                <td class="label">روش پرداخت</td>
                <td class="value">{{ $paymentLabel }}</td>
            </tr>
            @if($booking->veteran_type_applied)
            <tr>
                <td class="label">گروه ایثارگری</td>
                <td class="value">{{ $veteranLabel }}</td>
            </tr>
            @if($booking->secondary_veteran_type_applied)
            <tr>
                <td class="label">گروه دوم</td>
                <td class="value">{{ \App\Support\VeteranGroups::label($booking->secondary_veteran_type_applied, $booking->accommodation_id) }}</td>
            </tr>
            @endif
            @php
                $accGroupUsage = $pricing['veteran_accommodation_group_usage'] ?? $booking->veteran_accommodation_group_usage ?? [];
            @endphp
            @if(!empty($accGroupUsage))
            <tr>
                <td class="label">مصرف سقف اقامت</td>
                <td class="value" style="font-size:10px">
                    @foreach($accGroupUsage as $gKey => $gNights)
                        {{ \App\Support\VeteranGroups::label($gKey, $booking->accommodation_id) }}:
                        {{ \App\Support\PdfPersian::toPersianDigits((string) $gNights) }} شب
                        @if(!$loop->last) · @endif
                    @endforeach
                </td>
            </tr>
            @endif
            @endif
        </table>
    </div>

    <div class="section-title-bw">مشخصات افراد رزرو</div>
    <div class="info-card">
        @php
            $guestRowCount = max((int) $booking->guests, $booking->guestDetails->count());
            $guestDetailsByIndex = $booking->guestDetails->values();
            $bookerName = $booking->guest_contact_name ?? $booking->user->name;
        @endphp
        <table class="info-card-table guest-table">
            <tr class="header-row">
                <td style="width:34%">نام و نام خانوادگی</td>
                <td style="width:22%">کد ملی</td>
                <td style="width:22%">نسبت</td>
                <td style="width:22%">تاریخ تولد</td>
            </tr>
            @for($i = 0; $i < $guestRowCount; $i++)
            @php
                $guest = $guestDetailsByIndex->get($i);
                $guestName = filled($guest?->full_name) ? $guest->full_name : ($i === 0 ? $bookerName : '');
                $guestRelation = filled($guest?->relation) ? $guest->relation : ($i === 0 ? '—' : '');
                $hasGuestData = filled($guestName)
                    || filled($guest?->national_id)
                    || filled($guestRelation)
                    || filled($guest?->birth_date ?? null);
            @endphp
            <tr class="data-row">
                <td class="{{ $hasGuestData ? '' : 'writable' }}">{{ $guestName }}</td>
                <td class="{{ $hasGuestData ? '' : 'writable' }} ltr">{{ $guest?->national_id ?? '' }}</td>
                <td class="{{ $hasGuestData ? '' : 'writable' }}">{{ $guestRelation }}</td>
                <td class="{{ $hasGuestData ? '' : 'writable' }}">{{ $guest?->birth_date ?? '' }}</td>
            </tr>
            @endfor
        </table>
    </div>

    <div class="section-title">جزئیات مالی</div>
    <x-booking.financial-breakdown :booking="$booking" :pricing="$pricing" :pdf="true" />

    @if($booking->notes)
    <div class="section-title">یادداشت</div>
    <p>{{ $booking->notes }}</p>
    @endif

    <table class="signatures">
        <tr>
            <td>امضای مهمان</td>
            <td>مهر و امضای پذیرش</td>
        </tr>
    </table>
</body>
</html>
