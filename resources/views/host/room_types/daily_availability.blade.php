@extends('layouts.host')


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
/* Normal — all rooms free */
.day-cal-cell.c-free  { background:#ecfdf5; border-color:#6ee7b7; color:#065f46; }
/* Override with rooms > 0 */
.day-cal-cell.c-override { background:#eff6ff; border-color:#93c5fd; color:#1e40af; }
/* Override with 0 rooms (host closed) */
.day-cal-cell.c-override-zero {
    background:#fdf4ff; border-color:#d946ef; color:#86198f;
    background-image:repeating-linear-gradient(-45deg,transparent,transparent 4px,rgba(217,70,239,.12) 4px,rgba(217,70,239,.12) 5px);
}
/* Partially booked (no override) */
.day-cal-cell.c-partial { background:#fffbeb; border-color:#fcd34d; color:#92400e; }
/* Fully booked by guests */
.day-cal-cell.c-full {
    background:#f1f5f9; border-color:#cbd5e1; color:#94a3b8;
    background-image:repeating-linear-gradient(-45deg,transparent,transparent 4px,rgba(0,0,0,.06) 4px,rgba(0,0,0,.06) 5px);
}
/* Blocked */
.day-cal-cell.c-blocked {
    background:#fff0f0; border-color:#fca5a5; color:#dc2626;
    background-image:repeating-linear-gradient(-45deg,transparent,transparent 4px,rgba(220,38,38,.12) 4px,rgba(220,38,38,.12) 5px);
}
/* Override indicator badge */
.day-cal-cell.has-override::after {
    content:'✎'; position:absolute; top:1px; left:3px; font-size:8px; opacity:.7;
}
/* Price & interaction */
.day-cal-cell.clickable { cursor:pointer; }
.day-cal-cell.clickable:hover { filter:brightness(.92); }
.price-badge { font-size:7px; font-weight:700; line-height:1; margin-top:1px; white-space:nowrap; color:inherit; }
.disc-badge { position:absolute; top:1px; right:2px; font-size:7px; background:#dc2626; color:#fff; border-radius:2px; padding:0 2px; line-height:1.4; }
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
            <a wire:navigate href="{{ route('host.room-types.index', $accommodation) }}"><i class="bi bi-chevron-right me-1"></i>بازگشت</a>
            <span class="mx-1">·</span>{{ $accommodation->name }}
            <span class="mx-1">·</span>حداکثر ظرفیت کل: <strong>{{ $roomType->room_count }} اتاق</strong>
        </div>
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
                    تعداد اتاق‌های در دسترس را برای بازه انتخابی تعیین کنید.
                    مقدار صفر یعنی این اتاق برای آن روز بسته است.
                </p>
                <form action="{{ route('host.room-types.daily-availability.store', [$accommodation, $roomType]) }}" method="POST">
                    @csrf
                    @php $todayJ = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::today())->format('Y/m/d'); @endphp
                    <div class="mb-3">
                        <label class="form-label fw-semibold">از تاریخ <span class="text-danger">*</span></label>
                        <input type="text" name="date_from"
                               class="form-control @error('date_from') is-invalid @enderror"
                               placeholder="{{ $todayJ }}" value="{{ old('date_from', $todayJ) }}"
                               autocomplete="off" required>
                        <div class="form-text">تاریخ خورشیدی — مثال: ۱۴۰۵/۰۲/۲۰</div>
                        @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">تا تاریخ <span class="text-danger">*</span></label>
                        <input type="text" name="date_to"
                               class="form-control @error('date_to') is-invalid @enderror"
                               placeholder="{{ $todayJ }}" value="{{ old('date_to', $todayJ) }}"
                               autocomplete="off" required>
                        @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">تعداد اتاق موجود <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="available_count"
                                   class="form-control @error('available_count') is-invalid @enderror"
                                   min="0" max="{{ $roomType->room_count }}"
                                   value="{{ old('available_count', $roomType->room_count) }}" required>
                            <span class="input-group-text">از {{ $roomType->room_count }}</span>
                        </div>
                        <div class="form-text">صفر = بسته بودن کامل اتاق در آن بازه</div>
                        @error('available_count')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">قیمت سفارشی شب (اختیاری)</label>
                        <div class="input-group">
                            <input type="number" name="custom_price"
                                   class="form-control @error('custom_price') is-invalid @enderror"
                                   min="0" step="1000" placeholder="خالی = قیمت پایه تعریف‌شده"
                                   value="{{ old('custom_price') }}">
                            <span class="input-group-text">تومان</span>
                        </div>
                        @error('custom_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">تخفیف %</label>
                            <input type="number" name="discount_percentage"
                                   class="form-control @error('discount_percentage') is-invalid @enderror"
                                   min="0" max="100" placeholder="۰ تا ۱۰۰"
                                   value="{{ old('discount_percentage') }}">
                            @error('discount_percentage')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">برچسب قیمت</label>
                            <input type="text" name="price_label"
                                   class="form-control @error('price_label') is-invalid @enderror"
                                   placeholder="پیک، نوروز، تابستان..."
                                   maxlength="60" value="{{ old('price_label') }}">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">دلیل (اختیاری)</label>
                        <input type="text" name="reason" class="form-control"
                               placeholder="مثال: تعمیرات، نظافت عمیق..."
                               value="{{ old('reason') }}" maxlength="200">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-floppy me-2"></i>ذخیره تنظیمات
                    </button>
                </form>
            </div>
        </div>

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
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-white border-bottom rounded-top-4">
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar3 me-2"></i>نمای ظرفیت (۳ ماه آینده)</h6>
            </div>
            <div class="card-body p-4">
                @php
                    use Morilog\Jalali\Jalalian;
                    $nowD = new \DateTime('today');
                    for ($mi = 0; $mi < 3; $mi++):
                        $firstDay    = (clone $nowD)->modify("first day of +{$mi} month");
                        $lastDay     = (clone $firstDay)->modify('last day of this month');
                        $jFirst      = Jalalian::fromCarbon(\Carbon\Carbon::parse($firstDay->format('Y-m-d')));
                        $phpDow      = (int) $firstDay->format('N');
                        $offset      = ($phpDow + 1) % 7;
                        $daysInMonth = (int) $lastDay->format('d');
                @endphp

                <div class="mb-4">
                    <div class="fw-bold text-center mb-2">{{ $jFirst->format('F Y') }}</div>
                    <div class="day-cal-hdr">
                        @foreach(['ش','ی','د','س','چ','پ','ج'] as $dh)
                        <div class="day-cal-dh">{{ $dh }}</div>
                        @endforeach
                    </div>
                    <div class="day-cal">
                        @for($i = 0; $i < $offset; $i++)<div class="day-cal-cell empty"></div>@endfor
                        @for($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $dateObj  = (clone $firstDay)->modify('+' . ($d-1) . ' days');
                            $dateStr  = $dateObj->format('Y-m-d');
                            $isPast   = $dateStr < $nowD->format('Y-m-d');
                            $avail    = $availabilityMap[$dateStr] ?? null;
                            $jDay     = Jalalian::fromCarbon(\Carbon\Carbon::parse($dateStr))->getDay();
                            $cellCls  = 'c-free';
                            $subtitle = '';
                            $hasOvr   = false;
                            $cellDisc = 0; $cellLabel = ''; $hasPriceOvr = false; $priceDisplay = '';
                            if ($avail) {
                                $hasOvr     = $avail['has_override'];
                                $hasPriceOvr = $avail['has_price_override'] ?? false;
                                $cellDisc   = (int)($avail['discount_percentage'] ?? 0);
                                $cellLabel  = $avail['price_label'] ?? '';
                                $effPrice   = (int)($avail['effective_price'] ?? $avail['default_price'] ?? 0);
                                if ($effPrice > 0 && $hasPriceOvr) $priceDisplay = number_format($effPrice, 0, '.', ',') . 'ت';
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
                        @endphp
                        <div class="day-cal-cell {{ $cellCls }} {{ $isPast ? 'past' : '' }} {{ $hasOvr ? 'has-override' : '' }} {{ !$isPast ? 'clickable' : '' }}"
                             data-greg="{{ $dateStr }}"
                             data-jalali="{{ Jalalian::fromCarbon(\Carbon\Carbon::parse($dateStr))->format('Y/m/d') }}"
                             data-avail="{{ $avail['override_count'] ?? $roomType->room_count }}"
                             data-price="{{ $avail['custom_price'] ?? '' }}"
                             data-disc="{{ $cellDisc ?: '' }}"
                             data-label="{{ $cellLabel }}"
                             title="{{ $dateStr }}{{ $avail ? ' — ' . $avail['available_rooms'] . ' از ' . ($avail['total']) . ' اتاق آزاد' . ($hasOvr ? ' (تنظیم دستی)' : '') : '' }}"
                             @if(!$isPast) onclick="openDayModal(this,'{{ route('host.room-types.daily-availability.store', [$accommodation, $roomType]) }}')"
                             @endif>
                            @if($cellDisc > 0 && !$isPast)<div class="disc-badge">{{ $cellDisc }}%</div>@endif
                            <div class="cd">{{ $jDay }}</div>
                            @if($subtitle && !$isPast)<div class="cs">{{ $subtitle }}</div>@endif
                            @if($cellLabel && !$isPast)<div class="label-badge">{{ $cellLabel }}</div>@endif
                            @if($priceDisplay && !$isPast)<div class="price-badge">{{ $priceDisplay }}</div>@endif
                        </div>
                        @endfor
                    </div>
                </div>
                @php endfor; @endphp
            </div>
        </div>

        {{-- Overrides list --}}
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white border-bottom rounded-top-4">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ul me-2"></i>تنظیمات دستی فعال ({{ $overrides->count() }})</h6>
            </div>
            <div class="card-body p-0">
                @if($overrides->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    هیچ تنظیم دستی‌ای ثبت نشده است.
                </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>تاریخ خورشیدی</th>
                                <th>اتاق موجود</th>
                                <th>قیمت سفارشی</th>
                                <th>تخفیف</th>
                                <th>برچسب</th>
                                <th>دلیل</th>
                                <th class="text-end">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($overrides as $ov)
                        <tr>
                            <td class="fw-semibold">{{ \Morilog\Jalali\Jalalian::fromCarbon($ov->date)->format('Y/m/d') }}</td>
                            <td>
                                <span class="badge {{ $ov->available_count === 0 ? 'bg-danger' : 'bg-primary' }}">
                                    {{ $ov->available_count }} از {{ $roomType->room_count }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ $ov->custom_price ? number_format($ov->custom_price, 0, '.', ',') . ' ت' : '—' }}</td>
                            <td class="text-muted small">{{ $ov->discount_percentage ? $ov->discount_percentage . '%' : '—' }}</td>
                            <td class="text-muted small">{{ $ov->price_label ?: '—' }}</td>
                            <td class="text-muted">{{ $ov->reason ?: '—' }}</td>
                            <td class="text-end">
                                <form action="{{ route('host.room-types.daily-availability.destroy', [$accommodation, $roomType, $ov]) }}"
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
    const form = document.querySelector('form[action="' + formAction + '"]');
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
    if (priceEl) priceEl.value = cell.dataset.price || '';
    if (discEl)  discEl.value  = cell.dataset.disc  || '';
    if (lblEl)   lblEl.value   = cell.dataset.label || '';
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    form.querySelector('[name=date_from]').focus();
}
</script>
@endpush
