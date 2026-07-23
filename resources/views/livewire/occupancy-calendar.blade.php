@php $modalId = 'occ-day-modal-' . $this->getId(); @endphp

<div>
    <div class="ta-card">
        <div class="ta-card__head flex-wrap gap-2">
            <div>
                <h2 class="ta-card__title"><i class="bi bi-calendar3-week me-2 text-primary"></i>تقویم رزرو</h2>
                <div class="ta-card__sub">
                    روزهای پررنگ: رزرو فعال —
                    @if($occupancyCalendar['total_rooms'] > 0)
                        ظرفیت {{ $occupancyCalendar['total_rooms'] }} اتاق
                    @else
                        بدون ظرفیت ثبت‌شده
                    @endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="btn-group">
                    <button type="button" wire:click="prevCalendarMonth" class="btn btn-sm btn-light" title="ماه قبل" aria-label="ماه قبل">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <span class="btn btn-sm btn-light disabled fw-semibold" style="min-width:130px;opacity:1;color:inherit">
                        {{ $occupancyCalendar['month_label'] }}
                    </span>
                    <button type="button" wire:click="nextCalendarMonth" class="btn btn-sm btn-light" title="ماه بعد" aria-label="ماه بعد">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                </div>
                @unless($occupancyCalendar['is_current_month'] ?? true)
                <button type="button" wire:click="goToCalendarToday" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-calendar-event me-1"></i>ماه جاری
                </button>
                @endunless
                @if($showFilter && $accommodations->isNotEmpty())
                <div class="d-flex align-items-center gap-2">
                    <label class="text-muted small mb-0">اقامتگاه:</label>
                    <select wire:model.live="calendarAccommodationId" class="form-select form-select-sm" style="min-width:200px">
                        <option value="">همه اقامتگاه‌ها</option>
                        @foreach($accommodations as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
        </div>
        <div class="ta-card__body">
            <div class="d-flex flex-wrap gap-3 mb-3" style="font-size:.75rem">
                <span><span class="d-inline-block rounded" style="width:12px;height:12px;background:#ecfdf5;border:1px solid #6ee7b7"></span> آزاد</span>
                <span><span class="d-inline-block rounded" style="width:12px;height:12px;background:#fffbeb;border:1px solid #fcd34d"></span> رزرو جزئی</span>
                <span><span class="d-inline-block rounded" style="width:12px;height:12px;background:#f1f5f9;border:1px solid #cbd5e1"></span> تکمیل</span>
                <span><span class="d-inline-block rounded" style="width:12px;height:12px;box-shadow:0 0 0 2px var(--ta-brand-500)"></span> امروز</span>
            </div>
            <div class="occ-cal-hdr">
                @foreach(['ش','ی','د','س','چ','پ','ج'] as $dh)
                <div class="occ-cal-dh">{{ $dh }}</div>
                @endforeach
            </div>
            <div class="occ-cal-grid"
                 data-occ-cal-root
                 data-modal-id="{{ $modalId }}"
                 data-booking-url="{{ $bookingShowUrl }}">
                @foreach($occupancyCalendar['cells'] as $cell)
                @if(!$cell)
                <div class="occ-cal-cell empty"></div>
                @else
                <button type="button"
                        class="occ-cal-cell occ-cal-cell--day c-{{ $cell['state'] }} {{ $cell['is_past'] ? 'past' : '' }} {{ $cell['is_today'] ? 'is-today' : '' }}"
                        data-jalali="{{ $cell['jalali'] }}"
                        data-bookings='@json($cell['bookings'], JSON_HEX_APOS | JSON_HEX_QUOT)'
                        aria-label="جزئیات روز {{ $cell['day'] }}">
                    <span class="cd">{{ $cell['day'] }}</span>
                    @if($cell['booking_count'] > 0)
                    <span class="cs">{{ $cell['rooms_used'] }} اتاق</span>
                    @endif
                </button>
                @endif
                @endforeach
            </div>
        </div>
    </div>

    <div class="modal fade occ-day-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold occ-day-modal-title">جزئیات رزرو</h5>
                        <div class="text-muted small occ-day-modal-date"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
                </div>
                <div class="modal-body pt-2 occ-day-modal-body"></div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">بستن</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Styles/scripts must live inside the Livewire root element so deferred loads keep them. --}}
    <style>
.occ-cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; }
.occ-cal-hdr { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; margin-bottom:6px; }
.occ-cal-dh { text-align:center; font-size:.72rem; font-weight:600; color:#667085; padding:4px 0; }
.occ-cal-cell {
    aspect-ratio:1; border-radius:8px; display:flex; flex-direction:column;
    align-items:center; justify-content:center; font-size:.78rem; font-weight:600;
    border:1px solid transparent; position:relative; min-height:52px;
}
.occ-cal-cell .cd { line-height:1.1; }
.occ-cal-cell .cs { font-size:.62rem; font-weight:500; line-height:1; margin-top:2px; opacity:.85; }
.occ-cal-cell.empty { background:transparent; }
.occ-cal-cell.past { opacity:.45; }
.occ-cal-cell.c-free  { background:#ecfdf5; border-color:#6ee7b7; color:#065f46; }
.occ-cal-cell.c-partial { background:#fffbeb; border-color:#fcd34d; color:#92400e; }
.occ-cal-cell.c-full {
    background:#f1f5f9; border-color:#cbd5e1; color:#64748b;
    background-image:repeating-linear-gradient(-45deg,transparent,transparent 4px,rgba(0,0,0,.05) 4px,rgba(0,0,0,.05) 5px);
}
.occ-cal-cell.is-today { box-shadow:0 0 0 2px var(--ta-brand-500); }
.occ-cal-cell--day { cursor:pointer; border:none; padding:0; font:inherit; width:100%; transition:filter .15s, transform .1s; }
.occ-cal-cell--day:hover:not(.empty) { filter:brightness(.94); transform:scale(1.03); }
.occ-cal-cell--day:focus-visible { outline:2px solid var(--ta-brand-500); outline-offset:1px; }
.occ-day-modal .booking-row { border:1px solid #e4e7ec; border-radius:10px; padding:.75rem; margin-bottom:.5rem; }
.occ-day-modal .booking-row:last-child { margin-bottom:0; }
</style>
</div>
