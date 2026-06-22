@props([
    'calendarMonths',
    'availabilityMap',
    'roomType',
    'storeRoute' => null,
    'todayGreg' => null,
])

@php
    $todayGreg = $todayGreg ?? now()->toDateString();
@endphp

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
            $cellDisc = 0; $cellLabel = ''; $hasPriceOvr = false; $hasWeekly = false; $priceDisplay = '';
            if ($avail) {
                $hasOvr      = $avail['has_override'];
                $hasPriceOvr = $avail['has_price_override'] ?? false;
                $cellDisc    = (int)($avail['discount_percentage'] ?? 0);
                $cellLabel   = $avail['price_label'] ?? '';
                $hasWeekly   = $avail['has_weekly_rule'] ?? false;
                $effPrice    = (int)($avail['effective_price'] ?? $avail['default_price'] ?? 0);
                if ($effPrice > 0 && $hasPriceOvr) {
                    $priceDisplay = number_format($effPrice, 0, '.', ',') . 'ت';
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
            }
            $jalaliLabel = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($dateStr))->format('Y/m/d');
        @endphp
        <div class="day-cal-cell {{ $cellCls }} {{ $isPast ? 'past' : '' }} {{ $hasOvr ? 'has-override' : '' }} {{ !$isPast && $storeRoute ? 'clickable' : '' }}"
             data-greg="{{ $dateStr }}"
             data-jalali="{{ $jalaliLabel }}"
             data-avail="{{ $avail['override_count'] ?? $roomType->room_count }}"
             data-price="{{ $avail['custom_price'] ?? '' }}"
             data-disc="{{ $cellDisc ?: '' }}"
             data-label="{{ $cellLabel }}"
             data-weekday-col="{{ $cell['column'] }}"
             title="{{ $dateStr }}{{ $avail ? ' — ' . $avail['available_rooms'] . ' از ' . ($avail['total']) . ' آزاد' . ($hasOvr ? ' (تنظیم دستی)' : '') : '' }}"
             @if(!$isPast && $storeRoute) onclick="openDayModal(this,'{{ $storeRoute }}')"
             @endif>
            @if($cellDisc > 0 && !$isPast)<div class="disc-badge">{{ $cellDisc }}%</div>@endif
            @if($cellDisc < 0 && !$isPast)<div class="surcharge-badge">{{ $cellDisc }}%</div>@endif
            @if($hasWeekly && !$isPast)<div class="weekly-badge">هفتگی</div>@endif
            <div class="cd">{{ $jDay }}</div>
            @if($subtitle && !$isPast)<div class="cs">{{ $subtitle }}</div>@endif
            @if($cellLabel && !$isPast)<div class="label-badge">{{ $cellLabel }}</div>@endif
            @if($priceDisplay && !$isPast)<div class="price-badge">{{ $priceDisplay }}</div>@endif
        </div>
        @endif
        @endforeach
    </div>
</div>
@endforeach
