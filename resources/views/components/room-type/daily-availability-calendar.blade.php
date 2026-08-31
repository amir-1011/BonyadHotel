@props([
    'calendarMonths',
    'availabilityMap',
    'roomType',
    'storeRoute' => null,
    'todayGreg' => null,
])

@php
    $todayGreg = $todayGreg ?? now()->toDateString();
    $rateNameById = $roomType->rates->keyBy('id')->map(fn ($r) => $r->name);
@endphp

@once
@push('styles')
<style>
.day-cal { overflow: visible; }
.day-cal-cell {
    aspect-ratio: 1;
    min-height: 3.25rem;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: stretch;
    justify-content: flex-start;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid transparent;
    position: relative;
    overflow: visible;
    cursor: default;
    padding: 4px 3px 3px;
    gap: 2px;
}
.day-cal-cell.has-rate-prices {
    aspect-ratio: unset;
    min-height: 5.25rem;
}
.day-cal-cell .day-cal-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 2px;
    line-height: 1;
    padding: 0 1px;
    flex-shrink: 0;
}
.day-cal-cell .cd { font-size: 12px; font-weight: 700; line-height: 1; }
.day-cal-cell .cs {
    font-size: 7px;
    font-weight: 500;
    line-height: 1;
    opacity: .85;
    white-space: nowrap;
}
.day-cal-cell .rate-pill-row {
    display: flex;
    flex-wrap: wrap;
    gap: 3px;
    justify-content: center;
    align-items: center;
    margin-top: auto;
    padding-top: 4px;
    flex: 1;
    align-content: center;
}
.day-cal-cell .rate-pill {
    font-size: 11px;
    font-weight: 900;
    line-height: 1.2;
    border-radius: 5px;
    padding: 3px 6px;
    white-space: nowrap;
    border: 1px solid transparent;
    letter-spacing: -0.02em;
}
.day-cal-cell .rate-pill.is-disc {
    color: #b91c1c;
    background: #fef2f2;
    border-color: #fecaca;
}
.day-cal-cell .rate-pill.is-surcharge {
    color: #b45309;
    background: #fffbeb;
    border-color: #fde68a;
}
.day-cal-cell .rate-pill.is-custom {
    color: #1d4ed8;
    background: #eff6ff;
    border-color: #bfdbfe;
}
.day-cal-cell .rate-pill.is-more {
    color: #475569;
    background: #f8fafc;
    border-color: #e2e8f0;
}
.day-cal-cell:hover,
.day-cal-cell:focus-within {
    z-index: 30;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .14);
    transform: translateY(-1px);
    transition: box-shadow .15s, transform .15s;
}
.day-cal-cell.clickable { cursor: pointer; }
.day-cal-cell.past { opacity: .35; }
.day-cal-cell.empty { background: transparent; border-color: transparent; box-shadow: none !important; transform: none !important; }
.day-cal-cell.is-today:not(.past) { box-shadow: 0 0 0 2px #0d6efd; }
.day-cal-cell.has-override::after {
    content: '✎';
    position: absolute;
    top: 2px;
    left: 4px;
    font-size: 8px;
    opacity: .65;
}
.day-cal-cell .weekly-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 6px;
    background: #6366f1;
    color: #fff;
    border-radius: 3px;
    padding: 0 3px;
    line-height: 1.35;
}
.day-cal-cell .disc-badge,
.day-cal-cell .surcharge-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    font-size: 9px;
    font-weight: 800;
    border-radius: 4px;
    padding: 2px 4px;
    line-height: 1.2;
    color: #fff;
}
.day-cal-cell .disc-badge { background: #dc2626; }
.day-cal-cell .surcharge-badge { background: #d97706; }
.day-cal-cell .label-badge {
    font-size: 7px;
    opacity: .85;
    text-align: center;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
/* Rich hover tooltip */
.day-cal-tip {
    display: none;
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    transform: translateX(-50%);
    min-width: 210px;
    max-width: 260px;
    padding: 10px 11px;
    border-radius: 10px;
    background: #0f172a;
    color: #f8fafc;
    font-size: 11px;
    font-weight: 500;
    line-height: 1.45;
    text-align: right;
    box-shadow: 0 12px 32px rgba(15, 23, 42, .28);
    pointer-events: none;
    z-index: 40;
}
.day-cal-tip::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 6px solid transparent;
    border-top-color: #0f172a;
}
.day-cal-cell:hover .day-cal-tip,
.day-cal-cell:focus-within .day-cal-tip {
    display: block;
}
.day-cal-tip__date {
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 6px;
    color: #fff;
}
.day-cal-tip__meta {
    font-size: 10px;
    color: #cbd5e1;
    margin-bottom: 8px;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(148, 163, 184, .25);
}
.day-cal-tip__rates { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.day-cal-tip__rate-name {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #f1f5f9;
    margin-bottom: 2px;
}
.day-cal-tip__rate-val { font-size: 10px; font-weight: 600; }
.day-cal-tip__rate-val.is-disc { color: #fca5a5; }
.day-cal-tip__rate-val.is-surcharge { color: #fcd34d; }
.day-cal-tip__rate-val.is-custom { color: #93c5fd; }
.day-cal-tip__rate-label {
    display: block;
    font-size: 9px;
    color: #94a3b8;
    margin-top: 1px;
}
.day-cal-tip__weekly {
    display: inline-block;
    margin-top: 8px;
    font-size: 9px;
    background: #4f46e5;
    color: #fff;
    border-radius: 4px;
    padding: 1px 6px;
}
.day-cal-cell.cal-range-start,
.day-cal-cell.cal-range-end {
    box-shadow: 0 0 0 2px #0d6efd;
    z-index: 25;
}
.day-cal-cell.cal-range-between {
    background: rgba(13, 110, 253, .12) !important;
    border-color: rgba(13, 110, 253, .45) !important;
}
@media (max-width: 991px) {
    .day-cal-tip {
        left: auto;
        right: 0;
        transform: none;
        min-width: 180px;
    }
    .day-cal-tip::after { left: auto; right: 12px; transform: none; }
}
</style>
@endpush
@endonce

@foreach($calendarMonths as $month)
<div class="mb-4">
    <div class="fw-bold text-center mb-2">{{ $month['label'] }}</div>
    <div class="day-cal-hdr">
        @foreach(\App\Support\JalaliCalendarGrid::WEEKDAY_HEADERS as $dh)
        <div class="day-cal-dh">{{ $dh }}</div>
        @endforeach
    </div>
    <div class="day-cal">
        @foreach($month['cells'] as $cell)
        @if(!$cell)
        <div class="day-cal-cell empty"></div>
        @else
        @php
            $dateStr  = $cell['greg'];
            $isPast   = $dateStr < $todayGreg;
            $avail    = $availabilityMap[$dateStr] ?? null;
            $jDay     = $cell['jalali_day'];
            $cellCls  = 'c-free';
            $subtitle = '';
            $hasOvr   = false;
            $cellDisc = 0;
            $cellLabel = '';
            $hasWeekly = false;
            $rateOverrides = [];
            $ratePills = [];
            $tipRates = [];
            $capText = '';

            if ($avail) {
                $hasOvr      = $avail['has_override'];
                $cellDisc    = (int)($avail['discount_percentage'] ?? 0);
                $cellLabel   = $avail['price_label'] ?? '';
                $hasWeekly   = $avail['has_weekly_rule'] ?? false;
                $rateOverrides = $avail['rate_price_overrides'] ?? [];

                foreach ($rateOverrides as $rateId => $rp) {
                    $rateName = $rp['rate_name'] ?? ($rateNameById[$rateId] ?? 'تعرفه');
                    $disc = isset($rp['discount_percentage']) ? (int) $rp['discount_percentage'] : null;
                    $custom = isset($rp['custom_price']) ? (int) $rp['custom_price'] : null;
                    $label = $rp['price_label'] ?? '';
                    $eff = isset($rp['effective_price']) ? (int) $rp['effective_price'] : null;

                    $pillText = null;
                    $pillType = null;
                    $tipValue = null;
                    $tipType = null;

                    if ($disc !== null && $disc !== 0) {
                        $pillText = ($disc < 0 ? '' : '↑') . abs($disc) . '٪';
                        $pillType = $disc < 0 ? 'disc' : 'surcharge';
                        $tipValue = ($disc < 0 ? abs($disc) . '٪ تخفیف' : $disc . '٪ گران‌تر');
                        $tipType = $pillType;
                    } elseif ($custom !== null && $custom > 0) {
                        $pillText = \App\Support\PdfPersian::toPersianDigits(number_format($custom / 1000)) . 'K';
                        $pillType = 'custom';
                        $tipValue = \App\Support\PdfPersian::toPersianDigits(number_format($custom, 0, '.', ',')) . ' ریال / تخت';
                        $tipType = 'custom';
                    } elseif ($eff !== null && $eff > 0) {
                        $pillText = \App\Support\PdfPersian::toPersianDigits(number_format($eff / 1000)) . 'K';
                        $pillType = 'custom';
                        $tipValue = \App\Support\PdfPersian::toPersianDigits(number_format($eff, 0, '.', ',')) . ' ریال / تخت';
                        $tipType = 'custom';
                    } elseif ($label !== '') {
                        $pillText = '•';
                        $pillType = 'custom';
                        $tipValue = $label;
                        $tipType = 'custom';
                    }

                    if ($pillText !== null) {
                        $ratePills[] = ['text' => $pillText, 'type' => $pillType];
                        $tipRates[] = [
                            'name'  => $rateName,
                            'value' => $tipValue,
                            'type'  => $tipType,
                            'label' => $label,
                            'effective' => $eff,
                        ];
                    }
                }

                if ($avail['is_blocked']) {
                    $cellCls  = 'c-blocked';
                    $subtitle = 'مسدود';
                } elseif ($hasOvr && $avail['total'] === 0) {
                    $cellCls  = 'c-override-zero';
                    $subtitle = '۰ اتاق';
                } elseif ($hasOvr) {
                    $cellCls  = $avail['available_rooms'] <= 0 ? 'c-full' : 'c-override';
                    $subtitle = $avail['available_rooms'] . '/' . $avail['total'];
                } elseif ($avail['available_rooms'] <= 0) {
                    $cellCls  = 'c-full';
                    $subtitle = 'تمام';
                } elseif ($avail['booked'] > 0) {
                    $cellCls  = 'c-partial';
                    $subtitle = $avail['available_rooms'] . '/' . $avail['total'];
                } else {
                    $subtitle = $avail['total'] . ' اتاق';
                }

                $capText = $avail['available_rooms'] . ' از ' . $avail['total'] . ' اتاق آزاد';
                if ($hasOvr) {
                    $capText .= ' (ظرفیت دستی)';
                }
            }

            $jalaliLabel = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($dateStr))->format('Y/m/d');
            $ratePricesJson = !empty($rateOverrides)
                ? htmlspecialchars(json_encode($rateOverrides, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8')
                : '';

            $visiblePills = array_slice($ratePills, 0, 3);
            $hiddenPillCount = max(0, count($ratePills) - count($visiblePills));
            $showPerRateDisc = !$isPast && !empty($visiblePills);
            $showLegacyDisc  = !$isPast && !$showPerRateDisc && $cellDisc < 0;
            $showLegacySurcharge = !$isPast && !$showPerRateDisc && $cellDisc > 0;
            $showTip = !$isPast && ($avail !== null);
        @endphp
        <div class="day-cal-cell {{ $cellCls }} {{ $isPast ? 'past' : '' }} {{ $dateStr === $todayGreg ? 'is-today' : '' }} {{ $hasOvr ? 'has-override' : '' }} {{ $showPerRateDisc ? 'has-rate-prices' : '' }} {{ !$isPast && $storeRoute ? 'clickable' : '' }}"
             data-greg="{{ $dateStr }}"
             data-jalali="{{ $jalaliLabel }}"
             data-avail="{{ $avail['override_count'] ?? $roomType->room_count }}"
             data-disc="{{ $cellDisc ?: '' }}"
             data-label="{{ $cellLabel }}"
             data-rate-prices="{{ $ratePricesJson }}"
             data-weekday-col="{{ $cell['column'] }}"
             tabindex="{{ (!$isPast && $storeRoute) ? '0' : '-1' }}"
             @if(!$isPast && $storeRoute) onclick="openDayModal(this,'{{ $storeRoute }}')"
             @endif>
            @if($showLegacyDisc)<div class="disc-badge">{{ abs($cellDisc) }}٪</div>@endif
            @if($showLegacySurcharge)<div class="surcharge-badge">↑{{ $cellDisc }}٪</div>@endif
            @if($hasWeekly && !$isPast && !$showPerRateDisc)<div class="weekly-badge">هفتگی</div>@endif

            <div class="day-cal-head">
                <span class="cd">{{ $jDay }}</span>
                @if($subtitle && !$isPast)<span class="cs">{{ $subtitle }}</span>@endif
            </div>

            @if($showPerRateDisc)
            <div class="rate-pill-row">
                @foreach($visiblePills as $pill)
                <span class="rate-pill is-{{ $pill['type'] }}">{{ $pill['text'] }}</span>
                @endforeach
                @if($hiddenPillCount > 0)
                <span class="rate-pill is-more">+{{ $hiddenPillCount }}</span>
                @endif
            </div>
            @elseif($cellLabel && !$isPast)
            <div class="label-badge">{{ $cellLabel }}</div>
            @endif

            @if($showTip)
            <div class="day-cal-tip" role="tooltip">
                <div class="day-cal-tip__date">{{ $jalaliLabel }}</div>
                @if($capText)
                <div class="day-cal-tip__meta">{{ $capText }}</div>
                @endif
                @if(!empty($tipRates))
                <ul class="day-cal-tip__rates">
                    @foreach($tipRates as $tr)
                    <li>
                        <span class="day-cal-tip__rate-name">{{ $tr['name'] }}</span>
                        <span class="day-cal-tip__rate-val is-{{ $tr['type'] }}">{{ $tr['value'] }}</span>
                        @if(!empty($tr['effective']) && ($tr['type'] ?? '') !== 'custom')
                        <span class="day-cal-tip__rate-label">قیمت مؤثر: {{ \App\Support\PdfPersian::toPersianDigits(number_format($tr['effective'], 0, '.', ',')) }} ریال</span>
                        @endif
                        @if(!empty($tr['label']))
                        <span class="day-cal-tip__rate-label">{{ $tr['label'] }}</span>
                        @endif
                    </li>
                    @endforeach
                </ul>
                @elseif($cellDisc !== 0 || $cellLabel)
                <ul class="day-cal-tip__rates">
                    <li>
                        <span class="day-cal-tip__rate-name">همه تعرفه‌ها</span>
                        @if($cellDisc < 0)
                        <span class="day-cal-tip__rate-val is-disc">{{ abs($cellDisc) }}٪ تخفیف</span>
                        @elseif($cellDisc > 0)
                        <span class="day-cal-tip__rate-val is-surcharge">{{ $cellDisc }}٪ گران‌تر</span>
                        @endif
                        @if($cellLabel)
                        <span class="day-cal-tip__rate-label">{{ $cellLabel }}</span>
                        @endif
                    </li>
                </ul>
                @endif
                @if($hasWeekly)
                <span class="day-cal-tip__weekly">قانون هفتگی دائمی</span>
                @endif
            </div>
            @endif
        </div>
        @endif
        @endforeach
    </div>
</div>
@endforeach

@once
@push('scripts')
<script>
window._dailyCalRangePick = window._dailyCalRangePick || { awaitingEnd: false, startJal: '', startGreg: '' };

function _fillDailyFormFromCell(cell, form) {
    const avail = cell.dataset.avail;
    const availInput = form.querySelector('[name=available_count]');
    if (avail && availInput) availInput.value = avail;

    let ratePrices = null;
    if (cell.dataset.ratePrices) {
        try { ratePrices = JSON.parse(cell.dataset.ratePrices); } catch (e) {}
    }
    if (!ratePrices && (cell.dataset.disc || cell.dataset.label)) {
        ratePrices = {};
        form.querySelectorAll('.rate-adjustment-card').forEach(card => {
            ratePrices[card.dataset.rateId] = {
                discount_percentage: cell.dataset.disc || null,
                price_label: cell.dataset.label || null,
            };
        });
    }
    if (window.populateDailyAvailabilityRateFields) {
        window.populateDailyAvailabilityRateFields(ratePrices || {});
    }
}

function _clearCalRangeHighlight() {
    document.querySelectorAll('.day-cal-cell.cal-range-start, .day-cal-cell.cal-range-end, .day-cal-cell.cal-range-between')
        .forEach(el => el.classList.remove('cal-range-start', 'cal-range-end', 'cal-range-between'));
}

function _highlightCalRange(fromGreg, toGreg) {
    _clearCalRangeHighlight();
    document.querySelectorAll('.day-cal-cell[data-greg]').forEach(el => {
        const g = el.dataset.greg;
        if (!g || el.classList.contains('past') || g < fromGreg || g > toGreg) return;
        if (g === fromGreg) el.classList.add('cal-range-start');
        else if (g === toGreg) el.classList.add('cal-range-end');
        else el.classList.add('cal-range-between');
    });
}

function openDayModal(cell, formAction) {
    if (cell.classList.contains('past')) return;
    const form = document.getElementById('daily-availability-form') || document.querySelector('form[action="' + formAction + '"]');
    if (!form) return;

    const jal = cell.dataset.jalali;
    const greg = cell.dataset.greg;
    if (!jal || !greg) return;

    const dateFrom = form.querySelector('[name=date_from]');
    const dateTo = form.querySelector('[name=date_to]');
    const pick = window._dailyCalRangePick;

    if (!pick.awaitingEnd) {
        if (dateFrom) dateFrom.value = jal;
        if (dateTo) dateTo.value = jal;
        pick.awaitingEnd = true;
        pick.startJal = jal;
        pick.startGreg = greg;
        _highlightCalRange(greg, greg);
        _fillDailyFormFromCell(cell, form);
    } else {
        let fromJal = pick.startJal;
        let toJal = jal;
        let fromGreg = pick.startGreg;
        let toGreg = greg;

        if (toGreg < fromGreg) {
            [fromJal, toJal] = [toJal, fromJal];
            [fromGreg, toGreg] = [toGreg, fromGreg];
        }

        if (dateFrom) dateFrom.value = fromJal;
        if (dateTo) dateTo.value = toJal;
        pick.awaitingEnd = false;
        _highlightCalRange(fromGreg, toGreg);
    }

    if (window.BonyadJalaliDate) {
        window.BonyadJalaliDate.syncInputTodayClass(dateFrom);
        window.BonyadJalaliDate.syncInputTodayClass(dateTo);
    }

    if (!pick.awaitingEnd) {
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (dateFrom) dateFrom.focus();
    }
}
</script>
@endpush
@endonce
