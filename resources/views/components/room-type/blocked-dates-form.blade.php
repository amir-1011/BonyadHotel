@props(['accommodation', 'roomType', 'routePrefix', 'roomBookings' => []])

@php
    $physicalRooms = $roomType->rooms ?? collect();
    $oldRoomIds = collect(old('room_ids', []))->map(fn ($id) => (int) $id)->all();
    $todayJalali = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::today())->format('Y/m/d');
    $previewUrl = route($routePrefix . '.blocked-dates.preview', [$accommodation, $roomType]);
    $panel = str_starts_with($routePrefix, 'host.') ? 'host' : 'admin';
@endphp

<p class="text-muted small mb-3">
    بازه تاریخی را وارد کنید و اتاق‌های فیزیکی آزاد را انتخاب کنید.
    اتاق‌هایی که در همان بازه رزرو فعال دارند قابل مسدودسازی نیستند.
</p>

<x-host.can page="room-types.blocked-dates" action="write" :panel="$panel">
<form action="{{ route($routePrefix . '.blocked-dates.store', [$accommodation, $roomType]) }}" method="POST" id="blocked-dates-form">
    @csrf
    <div class="mb-3">
        <label class="form-label fw-semibold">از تاریخ <span class="text-danger">*</span></label>
        <input type="text" name="date_from"
               class="form-control blocked-date-input @error('date_from') is-invalid @enderror"
               placeholder="مثال: {{ $todayJalali }}"
               value="{{ old('date_from', $todayJalali) }}"
               autocomplete="off" required>
        <div class="form-text">تاریخ خورشیدی — مثال: ۱۴۰۵/۰۲/۲۰</div>
        @error('date_from')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">تا تاریخ <span class="text-danger">*</span></label>
        <input type="text" name="date_to"
               class="form-control blocked-date-input @error('date_to') is-invalid @enderror"
               placeholder="مثال: {{ $todayJalali }}"
               value="{{ old('date_to', $todayJalali) }}"
               autocomplete="off" required>
        <div class="form-text">تاریخ خورشیدی — مثال: ۱۴۰۵/۰۲/۲۵</div>
        @error('date_to')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div id="blocked-date-conflict-alert" class="alert alert-warning small py-2 d-none mb-3" role="alert"></div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label fw-semibold mb-0">اتاق‌های فیزیکی <span class="text-danger">*</span></label>
            @if($physicalRooms->isNotEmpty())
            <button type="button" class="btn btn-link btn-sm p-0" id="blocked-select-all-rooms">انتخاب همه</button>
            @endif
        </div>
        @error('room_ids')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        @if($physicalRooms->isEmpty())
        <div class="alert alert-warning small py-2 mb-0">
            <i class="bi bi-exclamation-triangle me-1"></i>
            اتاق فیزیکی تعریف نشده. ابتدا از
            <a href="{{ route($routePrefix . '.edit', [$accommodation, $roomType]) }}">صفحه ویرایش نوع اتاق</a>
            اتاق‌ها را تعریف کنید.
        </div>
        @else
        <div class="d-flex flex-wrap gap-2" id="blocked-room-picker">
            @foreach($physicalRooms as $index => $room)
            @php
                $bookings = $roomBookings[$room->id] ?? [];
            @endphp
            <label class="blocked-room-chip" data-room-id="{{ $room->id }}">
                <input type="checkbox"
                       name="room_ids[]"
                       value="{{ $room->id }}"
                       data-bookings='@json($bookings)'
                       @checked(in_array($room->id, $oldRoomIds, true))>
                <span class="blocked-room-chip__num">{{ $index + 1 }}</span>
                <span class="blocked-room-chip__name">{{ $room->name }}</span>
                <span class="blocked-room-chip__status small text-muted d-none"></span>
            </label>
            @endforeach
        </div>
        <div class="form-text mt-2">اتاق‌های رزروشده در بازه انتخابی غیرفعال می‌شوند.</div>
        @endif
    </div>

    <div class="mb-4">
        <label class="form-label fw-semibold">دلیل (اختیاری)</label>
        <input type="text" name="reason" class="form-control"
               placeholder="مثال: تعمیرات، رزرو شخصی..."
               value="{{ old('reason') }}" maxlength="200">
    </div>

    <button type="submit" class="btn btn-danger w-100" id="blocked-dates-submit" @disabled($physicalRooms->isEmpty())>
        <i class="bi bi-lock-fill me-2"></i>مسدود کردن
    </button>
</form>
</x-host.can>

@once
@push('styles')
<style>
.blocked-room-chip {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    border: 1.5px solid var(--bs-border-color);
    border-radius: 999px;
    padding: .35rem .7rem .35rem .5rem;
    cursor: pointer;
    user-select: none;
    margin: 0;
    background: var(--bs-body-bg);
    transition: border-color .12s, background .12s, opacity .12s;
}
.blocked-room-chip input { width: 1rem; height: 1rem; margin: 0; }
.blocked-room-chip__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 50%;
    background: rgba(var(--bs-danger-rgb), .12);
    color: var(--bs-danger);
    font-size: .72rem;
    font-weight: 700;
    flex-shrink: 0;
}
.blocked-room-chip__name { font-size: .8rem; font-weight: 600; max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.blocked-room-chip:has(input:checked) {
    border-color: var(--bs-danger);
    background: rgba(var(--bs-danger-rgb), .08);
}
.blocked-room-chip.is-booked-conflict {
    opacity: .55;
    cursor: not-allowed;
    border-color: var(--bs-secondary);
    background: var(--bs-secondary-bg);
}
.blocked-room-chip.is-booked-conflict:has(input:checked) {
    border-color: var(--bs-secondary);
    background: var(--bs-secondary-bg);
}
.blocked-room-chip.is-booked-conflict input {
    pointer-events: none;
}
.avail-cal-cell.clickable { cursor: pointer; }
.avail-cal-cell.cal-range-start,
.avail-cal-cell.cal-range-end {
    box-shadow: 0 0 0 2px #0d6efd;
    z-index: 2;
}
.avail-cal-cell.cal-range-between {
    background: rgba(13, 110, 253, .12) !important;
    border-color: rgba(13, 110, 253, .45) !important;
}
</style>
@endpush
@push('scripts')
<script>
document.addEventListener('click', function (e) {
    const btn = e.target.closest('#blocked-select-all-rooms');
    if (!btn) return;
    const picker = document.getElementById('blocked-room-picker');
    if (!picker) return;
    const boxes = picker.querySelectorAll('input[type="checkbox"]:not(:disabled)');
    const allChecked = Array.from(boxes).every(cb => cb.checked);
    boxes.forEach(cb => { cb.checked = !allChecked; });
    btn.textContent = allChecked ? 'انتخاب همه' : 'لغو انتخاب همه';
});

(function () {
    const form = document.getElementById('blocked-dates-form');
    if (!form) return;

    const previewUrl = @json($previewUrl);
    const dateInputs = form.querySelectorAll('.blocked-date-input');
    const alertEl = document.getElementById('blocked-date-conflict-alert');
    const submitBtn = document.getElementById('blocked-dates-submit');
    let previewTimer = null;

    if (window.BonyadJalaliDate) {
        dateInputs.forEach(function (input) {
            window.BonyadJalaliDate.bindInput(input);
        });
    }

    function setRoomConflictState(unavailableIds, conflicts) {
        const unavailable = new Set((unavailableIds || []).map(Number));
        form.querySelectorAll('.blocked-room-chip').forEach(chip => {
            const roomId = Number(chip.dataset.roomId);
            const input = chip.querySelector('input[type="checkbox"]');
            const status = chip.querySelector('.blocked-room-chip__status');
            const isConflict = unavailable.has(roomId);

            chip.classList.toggle('is-booked-conflict', isConflict);
            if (input) {
                input.disabled = isConflict;
                if (isConflict) input.checked = false;
            }
            if (status) {
                if (isConflict) {
                    status.textContent = 'رزرو شده';
                    status.classList.remove('d-none');
                } else {
                    status.textContent = '';
                    status.classList.add('d-none');
                }
            }
        });

        if (alertEl) {
            if (conflicts && conflicts.length) {
                const lines = conflicts.map(c => '«' + c.room_name + '»');
                alertEl.textContent = 'در این بازه، ' + lines.join('، ') + ' رزرو فعال دارد و قابل مسدودسازی نیست.';
                alertEl.classList.remove('d-none');
            } else {
                alertEl.textContent = '';
                alertEl.classList.add('d-none');
            }
        }

        if (submitBtn) {
            const anySelectable = form.querySelector('input[name="room_ids[]"]:not(:disabled)');
            submitBtn.disabled = !anySelectable;
        }
    }

    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(runPreview, 350);
    }

    function runPreview() {
        const dateFrom = form.querySelector('[name="date_from"]')?.value?.trim();
        const dateTo = form.querySelector('[name="date_to"]')?.value?.trim();
        if (!dateFrom || !dateTo) return;

        const params = new URLSearchParams({ date_from: dateFrom, date_to: dateTo });
        fetch(previewUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then(data => {
                if (data.errors) {
                    setRoomConflictState([], []);
                    return;
                }
                setRoomConflictState(data.unavailable_room_ids || [], data.conflicts || []);
            })
            .catch(() => setRoomConflictState([], []));
    }

    dateInputs.forEach(input => {
        input.addEventListener('input', schedulePreview);
        input.addEventListener('blur', schedulePreview);
    });

    runPreview();
})();

window._blockedCalRangePick = window._blockedCalRangePick || { awaitingEnd: false, startJal: '', startGreg: '' };

function _clearBlockedCalRangeHighlight() {
    document.querySelectorAll('.avail-cal-cell.cal-range-start, .avail-cal-cell.cal-range-end, .avail-cal-cell.cal-range-between')
        .forEach(el => el.classList.remove('cal-range-start', 'cal-range-end', 'cal-range-between'));
}

function _highlightBlockedCalRange(fromGreg, toGreg) {
    _clearBlockedCalRangeHighlight();
    document.querySelectorAll('.avail-cal-cell[data-greg]').forEach(el => {
        const g = el.dataset.greg;
        if (!g || el.classList.contains('past') || g < fromGreg || g > toGreg) return;
        if (g === fromGreg) el.classList.add('cal-range-start');
        else if (g === toGreg) el.classList.add('cal-range-end');
        else el.classList.add('cal-range-between');
    });
}

function pickBlockedDateRange(cell) {
    if (cell.classList.contains('past') || cell.classList.contains('empty')) return;
    const form = document.getElementById('blocked-dates-form');
    if (!form) return;

    const jal = cell.dataset.jalali;
    const greg = cell.dataset.greg;
    if (!jal || !greg) return;

    const dateFrom = form.querySelector('[name=date_from]');
    const dateTo = form.querySelector('[name=date_to]');
    const pick = window._blockedCalRangePick;

    if (!pick.awaitingEnd) {
        if (dateFrom) dateFrom.value = jal;
        if (dateTo) dateTo.value = jal;
        pick.awaitingEnd = true;
        pick.startJal = jal;
        pick.startGreg = greg;
        _highlightBlockedCalRange(greg, greg);
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
        _highlightBlockedCalRange(fromGreg, toGreg);
    }

    if (window.BonyadJalaliDate) {
        window.BonyadJalaliDate.syncInputTodayClass(dateFrom);
        window.BonyadJalaliDate.syncInputTodayClass(dateTo);
    }

    [dateFrom, dateTo].forEach(el => {
        if (el) el.dispatchEvent(new Event('input', { bubbles: true }));
    });

    if (!pick.awaitingEnd) {
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (dateFrom) dateFrom.focus();
    }
}
</script>
@endpush
@endonce
