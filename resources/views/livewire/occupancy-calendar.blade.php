@php $modalId = 'occ-day-modal-' . $this->getId(); @endphp

<div>
    <div class="ta-card occ-cal">
        <div class="ta-card__head occ-cal__head">
            <div class="occ-cal__heading min-w-0">
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
            <div class="occ-cal__toolbar">
                <div class="btn-group occ-cal__month-nav">
                    <button type="button" wire:click="prevCalendarMonth" class="btn btn-sm btn-light" title="ماه قبل" aria-label="ماه قبل">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <span class="btn btn-sm btn-light disabled fw-semibold occ-cal__month-label">
                        {{ $occupancyCalendar['month_label'] }}
                    </span>
                    <button type="button" wire:click="nextCalendarMonth" class="btn btn-sm btn-light" title="ماه بعد" aria-label="ماه بعد">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                </div>
                @unless($occupancyCalendar['is_current_month'] ?? true)
                <button type="button" wire:click="goToCalendarToday" class="btn btn-sm btn-outline-primary occ-cal__today-btn">
                    <i class="bi bi-calendar-event me-1"></i>ماه جاری
                </button>
                @endunless
                @if($showFilter && $accommodations->isNotEmpty())
                <div class="occ-cal__acc-filter">
                    <label class="text-muted small mb-0" for="occ-cal-acc-{{ $this->getId() }}">اقامتگاه:</label>
                    <select id="occ-cal-acc-{{ $this->getId() }}" wire:model.live="calendarAccommodationId" class="form-select form-select-sm occ-cal__acc-select">
                        <option value="">همه اقامتگاه‌ها</option>
                        @foreach($accommodations as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
        </div>
        <div class="ta-card__body occ-cal__body">
            <div class="occ-cal__legend">
                <span><span class="occ-cal__swatch occ-cal__swatch--free"></span> آزاد</span>
                <span><span class="occ-cal__swatch occ-cal__swatch--partial"></span> رزرو جزئی</span>
                <span><span class="occ-cal__swatch occ-cal__swatch--full"></span> تکمیل</span>
                <span><span class="occ-cal__swatch occ-cal__swatch--today"></span> امروز</span>
            </div>
            <div class="occ-cal__board">
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
                        <span class="cs">
                            <span class="cs-n">{{ $cell['rooms_used'] }}</span>
                            <span class="cs-u"> اتاق</span>
                        </span>
                        @endif
                    </button>
                    @endif
                    @endforeach
                </div>
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
.occ-cal { min-width: 0; }
.occ-cal__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.occ-cal__toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    min-width: 0;
}
.occ-cal__month-label {
    min-width: 7.5rem;
    opacity: 1;
    color: inherit;
}
.occ-cal__acc-filter {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
.occ-cal__acc-select {
    min-width: 12.5rem;
    max-width: 100%;
}
.occ-cal__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
    font-size: .75rem;
}
.occ-cal__swatch {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 4px;
    vertical-align: -1px;
    margin-left: 4px;
}
.occ-cal__swatch--free { background: #ecfdf5; border: 1px solid #6ee7b7; }
.occ-cal__swatch--partial { background: #fffbeb; border: 1px solid #fcd34d; }
.occ-cal__swatch--full { background: #f1f5f9; border: 1px solid #cbd5e1; }
.occ-cal__swatch--today { background: #fff; box-shadow: 0 0 0 2px var(--ta-brand-500); }
.occ-cal__board {
    min-width: 0;
}
.occ-cal-grid,
.occ-cal-hdr {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 4px;
}
.occ-cal-hdr { margin-bottom: 6px; }
.occ-cal-dh { text-align: center; font-size: .72rem; font-weight: 600; color: #667085; padding: 4px 0; }
.occ-cal-cell {
    aspect-ratio: 1;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: .78rem;
    font-weight: 600;
    border: 1px solid transparent;
    position: relative;
    min-height: 0;
    min-width: 0;
    overflow: hidden;
    padding: 2px;
}
.occ-cal-cell .cd { line-height: 1.1; }
.occ-cal-cell .cs {
    font-size: .62rem;
    font-weight: 500;
    line-height: 1;
    margin-top: 2px;
    opacity: .85;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.occ-cal-cell.empty { background: transparent; }
.occ-cal-cell.past { opacity: .45; }
.occ-cal-cell.c-free  { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }
.occ-cal-cell.c-partial { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
.occ-cal-cell.c-full {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #64748b;
    background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(0,0,0,.05) 4px, rgba(0,0,0,.05) 5px);
}
.occ-cal-cell.is-today { box-shadow: 0 0 0 2px var(--ta-brand-500); }
.occ-cal-cell--day {
    cursor: pointer;
    border: none;
    padding: 2px;
    font: inherit;
    width: 100%;
    touch-action: manipulation;
    transition: filter .15s, transform .1s;
}
@media (hover: hover) {
    .occ-cal-cell--day:hover:not(.empty) { filter: brightness(.94); transform: scale(1.03); }
}
.occ-cal-cell--day:focus-visible { outline: 2px solid var(--ta-brand-500); outline-offset: 1px; }
.occ-day-modal .booking-row { border: 1px solid #e4e7ec; border-radius: 10px; padding: .75rem; margin-bottom: .5rem; }
.occ-day-modal .booking-row:last-child { margin-bottom: 0; }

@media (max-width: 767.98px) {
    .occ-cal__toolbar { width: 100%; }
    .occ-cal__month-nav { flex: 1 1 auto; }
    .occ-cal__month-label { flex: 1 1 auto; min-width: 0; }
    .occ-cal__acc-filter { width: 100%; }
    .occ-cal__acc-select { min-width: 0; flex: 1 1 auto; }
    .occ-cal__legend { gap: 8px 10px; font-size: .7rem; margin-bottom: 10px; }
    .occ-cal-grid,
    .occ-cal-hdr { gap: 3px; }
    .occ-cal-dh { font-size: .65rem; padding: 2px 0; }
    .occ-cal-cell { font-size: .72rem; border-radius: 6px; }
    .occ-cal-cell .cs { font-size: .58rem; }
    .occ-cal-cell .cs-u { display: none; }
}

@media (max-width: 575.98px) {
    .occ-cal-cell { font-size: .68rem; }
    .occ-cal-cell .cd { font-size: .7rem; }
    .occ-cal-cell .cs { font-size: .52rem; margin-top: 1px; }
}
</style>
</div>
