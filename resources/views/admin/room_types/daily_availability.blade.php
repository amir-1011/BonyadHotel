@extends('layouts.admin')


@push('styles')
<style>
.day-cal { display:grid; grid-template-columns:repeat(7,1fr); gap:3px; }
.day-cal-hdr { display:grid; grid-template-columns:repeat(7,1fr); gap:3px; margin-bottom:4px; }
.day-cal-dh { text-align:center; font-size:11px; font-weight:600; color:#6c757d; padding:3px 0; }
.day-cal-cell {
    aspect-ratio:1; border-radius:6px; display:flex; flex-direction:column;
    align-items:center; justify-content:center; font-size:12px; font-weight:600;
    border:1px solid transparent; position:relative; overflow:hidden; cursor:default;
}
.day-cal-cell .cd { line-height:1; }
.day-cal-cell .cs { font-size:9px; font-weight:400; line-height:1; margin-top:2px; }
.day-cal-cell.past { opacity:.3; }
.day-cal-cell.empty { background:transparent; border-color:transparent; }
.day-cal-cell.c-free     { background:#ecfdf5; border-color:#6ee7b7; color:#065f46; }
.day-cal-cell.c-override { background:#eff6ff; border-color:#93c5fd; color:#1e40af; }
.day-cal-cell.c-override-zero {
    background:#fdf4ff; border-color:#d946ef; color:#86198f;
    background-image:repeating-linear-gradient(-45deg,transparent,transparent 4px,rgba(217,70,239,.12) 4px,rgba(217,70,239,.12) 5px);
}
.day-cal-cell.c-partial   { background:#fffbeb; border-color:#fcd34d; color:#92400e; }
.day-cal-cell.c-full {
    background:#f1f5f9; border-color:#cbd5e1; color:#94a3b8;
    background-image:repeating-linear-gradient(-45deg,transparent,transparent 4px,rgba(0,0,0,.06) 4px,rgba(0,0,0,.06) 5px);
}
.day-cal-cell.c-blocked {
    background:#fff0f0; border-color:#fca5a5; color:#dc2626;
    background-image:repeating-linear-gradient(-45deg,transparent,transparent 4px,rgba(220,38,38,.12) 4px,rgba(220,38,38,.12) 5px);
}
.day-cal-cell.has-override::after { content:'✎'; position:absolute; top:1px; left:3px; font-size:8px; opacity:.7; }
/* Price & interaction */
.day-cal-cell.has-price-ovr { cursor:pointer; }
.day-cal-cell.has-price-ovr:hover { filter:brightness(.92); transform:scale(1.06); z-index:1; transition:.1s; }
.day-cal-cell.clickable { cursor:pointer; }
.day-cal-cell.clickable:hover { filter:brightness(.92); }
.price-badge { font-size:7px; font-weight:700; line-height:1; margin-top:1px; white-space:nowrap; color:inherit; }
.disc-badge { position:absolute; top:1px; right:2px; font-size:7px; background:#dc2626; color:#fff; border-radius:2px; padding:0 2px; line-height:1.4; }
.surcharge-badge { position:absolute; top:1px; right:2px; font-size:7px; background:#d97706; color:#fff; border-radius:2px; padding:0 2px; line-height:1.4; }
.weekly-badge { position:absolute; bottom:1px; left:2px; font-size:6px; background:#6366f1; color:#fff; border-radius:2px; padding:0 2px; line-height:1.3; }
.label-badge { font-size:7px; opacity:.8; margin-top:1px; line-height:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
</style>
@endpush

@section('content')

<div>


<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h5 class="fw-bold mb-1">
            <i class="bi bi-sliders me-2 text-primary"></i>تنظیم ظرفیت روزانه — {{ $roomType->name }}
        </h5>
        <div class="text-muted small">
            <a wire:navigate href="{{ route('admin.room-types.index', $accommodation) }}"><i class="bi bi-chevron-right me-1"></i>بازگشت</a>
            <span class="mx-1">·</span>{{ $accommodation->name }}
            <span class="mx-1">·</span>حداکثر ظرفیت کل: <strong>{{ $roomType->room_count }} اتاق</strong>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white rounded-top-4 border-0">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i>تنظیم ظرفیت برای بازه</h6>
            </div>
            <div class="card-body p-4">
                <x-room-type.daily-availability-form
                    :room-type="$roomType"
                    :store-route="route('admin.room-types.daily-availability.store', [$accommodation, $roomType])" />
            </div>
        </div>

        <x-room-type.weekly-rules-table
            :weekly-rules="$weeklyRules"
            destroy-route-name="admin.room-types.weekly-price-rules.destroy"
            :accommodation="$accommodation"
            :room-type="$roomType" />

        <div class="card shadow-sm border-0 rounded-4 mt-3">
            <div class="card-body p-3">
                <div class="fw-semibold small mb-2">راهنمای رنگ‌ها:</div>
                <div class="d-flex flex-column gap-2 small text-muted">
                    <div class="d-flex gap-2 align-items-center"><div style="width:18px;height:18px;border-radius:4px;background:#ecfdf5;border:1px solid #6ee7b7;flex-shrink:0"></div>موجود — همه اتاق‌ها آزاد</div>
                    <div class="d-flex gap-2 align-items-center"><div style="width:18px;height:18px;border-radius:4px;background:#eff6ff;border:1px solid #93c5fd;flex-shrink:0"></div>تنظیم دستی — تعداد کاهش یافته</div>
                    <div class="d-flex gap-2 align-items-center"><div style="width:18px;height:18px;border-radius:4px;background:#fdf4ff;border:1px solid #d946ef;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(217,70,239,.15) 3px,rgba(217,70,239,.15) 4px);flex-shrink:0"></div>تنظیم دستی — ۰ اتاق (بسته)</div>
                    <div class="d-flex gap-2 align-items-center"><div style="width:18px;height:18px;border-radius:4px;background:#fffbeb;border:1px solid #fcd34d;flex-shrink:0"></div>جزئاً رزرو شده</div>
                    <div class="d-flex gap-2 align-items-center"><div style="width:18px;height:18px;border-radius:4px;background:#f1f5f9;border:1px solid #cbd5e1;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(0,0,0,.08) 3px,rgba(0,0,0,.08) 4px);flex-shrink:0"></div>تمام شده — رزرو مهمان</div>
                    <div class="d-flex gap-2 align-items-center"><div style="width:18px;height:18px;border-radius:4px;background:#fff0f0;border:1px solid #fca5a5;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(220,38,38,.15) 3px,rgba(220,38,38,.15) 4px);flex-shrink:0"></div>مسدود شده</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white border-bottom rounded-top-4">
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar3 me-2"></i>نمای ظرفیت (۳ ماه آینده)</h6>
            </div>
            <div class="card-body p-4">
                <x-room-type.daily-availability-calendar
                    :calendar-months="$calendarMonths"
                    :availability-map="$availabilityMap"
                    :room-type="$roomType"
                    :store-route="route('admin.room-types.daily-availability.store', [$accommodation, $roomType])" />
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-bottom rounded-top-4">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>تنظیمات دستی فعال ({{ $overrides->count() }})</h6>
            </div>
            <div class="card-body p-0">
                @if($overrides->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>هیچ تنظیم دستی‌ای ثبت نشده است.
                </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>تاریخ خورشیدی</th><th>اتاق موجود</th><th>قیمت سفارشی</th><th>تخفیف</th><th>برچسب</th><th>دلیل</th><th class="text-end">عملیات</th></tr>
                        </thead>
                        <tbody>
                        @foreach($overrides as $ov)
                        <tr>
                            <td class="fw-semibold">{{ \Morilog\Jalali\Jalalian::fromCarbon($ov->date)->format('Y/m/d') }}</td>
                            <td><span class="badge {{ $ov->available_count === 0 ? 'bg-danger' : 'bg-primary' }}">{{ $ov->available_count }} از {{ $roomType->room_count }}</span></td>
                            <td class="text-muted small">{{ $ov->custom_price ? number_format($ov->custom_price, 0, '.', ',') . ' ت' : '—' }}</td>
                            <td class="text-muted small">
                                @if($ov->discount_percentage > 0)
                                    {{ $ov->discount_percentage }}% تخفیف
                                @elseif($ov->discount_percentage < 0)
                                    {{ $ov->discount_percentage }}% (گران‌تر)
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-muted small">{{ $ov->price_label ?: '—' }}</td>
                            <td class="text-muted">{{ $ov->reason ?: '—' }}</td>
                            <td class="text-end">
                                <form action="{{ route('admin.room-types.daily-availability.destroy', [$accommodation, $roomType, $ov]) }}"
                                      method="POST" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" data-swal-confirm="این تنظیم حذف شود؟" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

</div>

</div>

@endsection

@push('scripts')
<script>
function openDayModal(cell, formAction) {
    if (cell.classList.contains('past')) return;
    const form = document.getElementById('daily-availability-form') || document.querySelector('form[action="' + formAction + '"]');
    if (!form) return;
    const jal = cell.dataset.jalali;
    if (jal) {
        form.querySelector('[name=date_from]').value = jal;
        form.querySelector('[name=date_to]').value = jal;
    }
    const avail = cell.dataset.avail;
    if (avail) form.querySelector('[name=available_count]').value = avail;
    const priceEl = form.querySelector('[name=custom_price]');
    const discEl  = form.querySelector('[name=discount_percentage]');
    const lblEl   = form.querySelector('[name=price_label]');
    if (priceEl) priceEl.value = cell.dataset.price ? (window.formatMoney ? window.formatMoney(cell.dataset.price) : cell.dataset.price) : '';
    if (discEl)  discEl.value  = cell.dataset.disc  || '';
    if (lblEl)   lblEl.value   = cell.dataset.label || '';
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    form.querySelector('[name=date_from]').focus();
}
</script>
@endpush
