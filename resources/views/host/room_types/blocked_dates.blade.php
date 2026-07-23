@extends('layouts.host')

@section('pageTitle')
مسدودسازی تاریخ — {{ $roomType->name }}
@endsection


@push('styles')
<style>
.avail-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.avail-cal-header { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 4px; }
.avail-cal-dh { text-align: center; font-size: 11px; font-weight: 600; color: #6c757d; padding: 4px 0; }
.avail-cal-cell {
    aspect-ratio: 1;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid transparent;
    position: relative;
    overflow: hidden;
}
.avail-cal-cell .cell-day { line-height: 1; }
.avail-cal-cell .cell-rooms { font-size: 9px; font-weight: 400; line-height: 1; margin-top: 2px; }
.avail-cal-cell.past { opacity: .3; }
.avail-cal-cell.empty { background: transparent; border-color: transparent; }
.avail-cal-cell.fully-free { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
.avail-cal-cell.partially-booked { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
.avail-cal-cell.fully-booked {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #94a3b8;
    background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(0,0,0,.06) 4px, rgba(0,0,0,.06) 5px);
}
.avail-cal-cell.host-blocked {
    background: #fff0f0;
    border-color: #fca5a5;
    color: #dc2626;
    background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(220,38,38,.12) 4px, rgba(220,38,38,.12) 5px);
}
.avail-cal-cell.host-blocked::after {
    content: '✕';
    position: absolute;
    font-size: 10px;
    bottom: 1px;
    left: 50%;
    transform: translateX(-50%);
    color: #dc2626;
    opacity: .6;
}
.avail-cal-cell.is-today:not(.past) { box-shadow: 0 0 0 2px #0d6efd; }
</style>
@endpush

@section('content')
<div>


{{-- Breadcrumb --}}
<div class="mb-3">
    <div class="text-muted small">
        <a wire:navigate href="{{ route('host.room-types.index', $accommodation) }}"><i class="bi bi-chevron-right me-1"></i>بازگشت به اتاق‌ها</a>
        <span class="mx-1">·</span>
        <span>{{ $accommodation->name }}</span>
        <span class="mx-1">·</span>
        <span>{{ $roomType->room_count }} اتاق در کل</span>
    </div>
</div>

<div class="row g-4">

    {{-- Left: Add block form --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-danger text-white rounded-top-4 border-0">
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-plus me-2"></i>مسدود کردن تاریخ جدید</h6>
            </div>
            <div class="card-body p-4">
                <x-room-type.blocked-dates-form
                    :accommodation="$accommodation"
                    :roomType="$roomType"
                    route-prefix="host.room-types"
                    :room-bookings="$roomBookings ?? []"
                />
            </div>
        </div>

        <x-room-type.blocked-dates-list
            :blocked-dates="$blockedDates"
            :room-type="$roomType"
            :accommodation="$accommodation"
            range-destroy-route-name="host.room-types.blocked-dates-range.destroy" />

        {{-- Legend --}}
        <div class="card shadow-sm border-0 rounded-4 mt-3">
            <div class="card-body p-3">
                <div class="fw-semibold small mb-2">راهنمای رنگ‌ها:</div>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:20px;height:20px;border-radius:4px;background:#ecfdf5;border:1px solid #6ee7b7;flex-shrink:0;"></div>
                        <span class="small text-muted">موجود — تمام اتاق‌ها آزاد</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:20px;height:20px;border-radius:4px;background:#fffbeb;border:1px solid #fcd34d;flex-shrink:0;"></div>
                        <span class="small text-muted">جزئاً رزرو شده</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:20px;height:20px;border-radius:4px;background:#f1f5f9;border:1px solid #cbd5e1;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(0,0,0,.08) 3px,rgba(0,0,0,.08) 4px);flex-shrink:0;"></div>
                        <span class="small text-muted">تمام شده — رزروهای مهمانان</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:20px;height:20px;border-radius:4px;background:#fff0f0;border:1px solid #fca5a5;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(220,38,38,.15) 3px,rgba(220,38,38,.15) 4px);flex-shrink:0;"></div>
                        <span class="small text-muted">مسدود شده توسط میزبان</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: Calendar overview --}}
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white border-bottom rounded-top-4">
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar3 me-2"></i>نمای ظرفیت (۳ ماه آینده)</h6>
            </div>
            <div class="card-body p-4">
                @php
                    use Morilog\Jalali\Jalalian;
                    $months = [];
                    $now    = new \DateTime('today');
                    for ($mi = 0; $mi < 3; $mi++) {
                        $firstDay  = (clone $now)->modify("first day of +{$mi} month");
                        $lastDay   = (clone $firstDay)->modify('last day of this month');
                        $firstGrD  = $firstDay->format('Y-m-d');
                        $lastGrD   = $lastDay->format('Y-m-d');
                        $jFirst    = Jalalian::fromCarbon(\Carbon\Carbon::parse($firstGrD));
                        $months[]  = [
                            'label'    => $jFirst->format('F Y'),
                            'firstDay' => $firstDay,
                            'lastDay'  => $lastDay,
                        ];
                    }
                    $DOW_FA = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];
                @endphp

                @foreach($months as $monthData)
                @php
                    $fd       = $monthData['firstDay'];
                    $ld       = $monthData['lastDay'];
                    // Jalali weekday offset: 0=Sat … 6=Fri
                    // PHP date('N'): 1=Mon … 7=Sun
                    // Sat = PHP 6 → offset 0; Sun=PHP 7→1; Mon=PHP 1→2; Tue=PHP 2→3; Wed=PHP 3→4; Thu=PHP 4→5; Fri=PHP 5→6
                    $phpDow   = (int) $fd->format('N');
                    $offset   = ($phpDow + 1) % 7; // Sat=0, Sun=1 ... Fri=6
                    $daysInMonth = (int) $ld->format('d');
                @endphp
                <div class="mb-4">
                    <div class="fw-bold text-center mb-2">{{ $monthData['label'] }}</div>
                    <div class="avail-cal-header">
                        @foreach(['ش','ی','د','س','چ','پ','ج'] as $dh)
                        <div class="avail-cal-dh">{{ $dh }}</div>
                        @endforeach
                    </div>
                    <div class="avail-calendar">
                        @for($i = 0; $i < $offset; $i++)<div class="avail-cal-cell empty"></div>@endfor
                        @for($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $dateObj = (clone $fd)->modify('+' . ($d-1) . ' days');
                            $dateStr = $dateObj->format('Y-m-d');
                            $isPast  = $dateStr < now()->toDateString();
                            $avail   = $availabilityMap[$dateStr] ?? null;
                            $cellClass = 'fully-free';
                            $roomsInfo = '';
                            if ($avail) {
                                if ($avail['is_blocked']) {
                                    $cellClass = 'host-blocked';
                                    $roomsInfo = 'مسدود';
                                } elseif (!empty($avail['is_partially_blocked'])) {
                                    $cellClass = 'partially-booked';
                                    $roomsInfo = ($avail['blocked_rooms'] ?? 0) . ' مسدود';
                                } elseif ($avail['available_rooms'] <= 0) {
                                    $cellClass = 'fully-booked';
                                    $roomsInfo = 'تمام شد';
                                } elseif ($avail['booked'] > 0) {
                                    $cellClass = 'partially-booked';
                                    $roomsInfo = $avail['available_rooms'] . '/' . $avail['total'];
                                } else {
                                    $cellClass = 'fully-free';
                                    $roomsInfo = $avail['total'] . ' اتاق';
                                }
                            }
                            $jDay = Jalalian::fromCarbon(\Carbon\Carbon::parse($dateStr))->getDay();
                            $jalaliLabel = Jalalian::fromCarbon(\Carbon\Carbon::parse($dateStr))->format('Y/m/d');
                        @endphp
                        <div class="avail-cal-cell {{ $cellClass }} {{ $isPast ? 'past' : '' }} {{ $dateStr === now()->toDateString() ? 'is-today' : '' }}{{ $isPast ? '' : ' clickable' }}"
                             data-greg="{{ $dateStr }}"
                             data-jalali="{{ $jalaliLabel }}"
                             @if(!$isPast) onclick="pickBlockedDateRange(this)" @endif
                             title="{{ $dateStr }}@if($avail && $avail['is_blocked']) — مسدود@elseif($avail) — {{ $avail['available_rooms'] }} اتاق آزاد از {{ $avail['total'] }}@endif">
                            <div class="cell-day">{{ $jDay }}</div>
                            @if($roomsInfo && !$isPast)<div class="cell-rooms">{{ $roomsInfo }}</div>@endif
                        </div>
                        @endfor
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</div>

</div>
@endsection
