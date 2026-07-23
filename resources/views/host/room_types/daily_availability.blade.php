@extends('layouts.host')

@section('pageTitle')
سیاست‌های قیمتی — {{ $roomType->name }}
@endsection


@push('styles')
<style>
.day-cal { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; overflow:visible; }
.day-cal-hdr { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; margin-bottom:4px; }
.day-cal-dh { text-align:center; font-size:11px; font-weight:600; color:#6c757d; padding:3px 0; }
.day-cal-cell.c-free  { background:#ecfdf5; border-color:#6ee7b7; color:#065f46; }
.day-cal-cell.c-override { background:#eff6ff; border-color:#93c5fd; color:#1e40af; }
.day-cal-cell.c-override-zero {
    background:#fdf4ff; border-color:#d946ef; color:#86198f;
    background-image:repeating-linear-gradient(-45deg,transparent,transparent 4px,rgba(217,70,239,.12) 4px,rgba(217,70,239,.12) 5px);
}
.day-cal-cell.c-partial { background:#fffbeb; border-color:#fcd34d; color:#92400e; }
.day-cal-cell.c-full {
    background:#f1f5f9; border-color:#cbd5e1; color:#94a3b8;
    background-image:repeating-linear-gradient(-45deg,transparent,transparent 4px,rgba(0,0,0,.06) 4px,rgba(0,0,0,.06) 5px);
}
.day-cal-cell.c-blocked {
    background:#fff0f0; border-color:#fca5a5; color:#dc2626;
    background-image:repeating-linear-gradient(-45deg,transparent,transparent 4px,rgba(220,38,38,.12) 4px,rgba(220,38,38,.12) 5px);
}
</style>
@endpush

@section('content')

<div>


<div class="mb-3">
    <div class="text-muted small">
        <a wire:navigate href="{{ route('host.room-types.index', $accommodation) }}"><i class="bi bi-chevron-right me-1"></i>بازگشت</a>
        <span class="mx-1">·</span>{{ $accommodation->name }}
        <span class="mx-1">·</span>حداکثر ظرفیت کل: <strong>{{ $roomType->room_count }} اتاق</strong>
    </div>
</div>

<div class="row g-4">

    {{-- Left: form --}}
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white rounded-top-4 border-0">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>تنظیم ظرفیت برای بازه</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted small mb-3">
                    تعداد اتاق و قیمت هر تعرفه را برای بازه یا به‌صورت دائمی هفتگی تنظیم کنید.
                    از فیلتر روزهای هفته برای اعمال هوشمند استفاده کنید.
                </p>
                <x-room-type.daily-availability-form
                    :room-type="$roomType"
                    :store-route="route('host.room-types.daily-availability.store', [$accommodation, $roomType])" />
            </div>
        </div>

        <x-room-type.weekly-rules-table
            :weekly-rules="$weeklyRules"
            :rate-weekly-rules="$rateWeeklyRules"
            destroy-route-name="host.room-types.weekly-price-rules.destroy"
            rate-destroy-route-name="host.room-types.rate-weekly-price-rules.destroy"
            :accommodation="$accommodation"
            :room-type="$roomType" />

        <x-room-type.daily-overrides-list
            :override-ranges="$overrideRanges"
            :room-type="$roomType"
            :accommodation="$accommodation"
            range-destroy-route-name="host.room-types.daily-availability-range.destroy" />

        {{-- Legend --}}
        <div class="card shadow-sm border-0 rounded-4 mt-3">
            <div class="card-body p-3">
                <div class="fw-semibold small mb-2">راهنمای رنگ‌ها:</div>
                <div class="d-flex flex-column gap-2 small text-muted">
                    <div class="d-flex gap-2 align-items-center">
                        <div style="width:18px;height:18px;border-radius:4px;background:#ecfdf5;border:1px solid #6ee7b7;flex-shrink:0"></div>موجود — همه اتاق‌ها آزاد
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <div style="width:18px;height:18px;border-radius:4px;background:#eff6ff;border:1px solid #93c5fd;flex-shrink:0"></div>تنظیم دستی — تعداد کاهش یافته
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <div style="width:18px;height:18px;border-radius:4px;background:#fdf4ff;border:1px solid #d946ef;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(217,70,239,.15) 3px,rgba(217,70,239,.15) 4px);flex-shrink:0"></div>تنظیم دستی — ۰ اتاق (بسته)
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <div style="width:18px;height:18px;border-radius:4px;background:#fffbeb;border:1px solid #fcd34d;flex-shrink:0"></div>جزئاً رزرو شده
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <div style="width:18px;height:18px;border-radius:4px;background:#f1f5f9;border:1px solid #cbd5e1;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(0,0,0,.08) 3px,rgba(0,0,0,.08) 4px);flex-shrink:0"></div>تمام شده — رزرو مهمان
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <div style="width:18px;height:18px;border-radius:4px;background:#fff0f0;border:1px solid #fca5a5;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(220,38,38,.15) 3px,rgba(220,38,38,.15) 4px);flex-shrink:0"></div>مسدود شده (تقویم مسدودی)
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right: calendar + overrides table --}}
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-bottom rounded-top-4">
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar3 me-2"></i>نمای ظرفیت (۳ ماه آینده)</h6>
            </div>
            <div class="card-body p-4">
                <x-room-type.daily-availability-calendar
                    :calendar-months="$calendarMonths"
                    :availability-map="$availabilityMap"
                    :room-type="$roomType"
                    :store-route="route('host.room-types.daily-availability.store', [$accommodation, $roomType])" />
            </div>
        </div>
    </div>

</div>

</div>

@endsection
