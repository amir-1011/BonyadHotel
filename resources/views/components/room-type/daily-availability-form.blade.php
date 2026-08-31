@props([
    'roomType',
    'storeRoute',
    'weeklyDestroyRoutePrefix' => null,
])

@php
    use App\Models\RoomTypeWeeklyPriceRule;
    $todayJ = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::today())->format('Y/m/d');
    $panel = str_contains($storeRoute ?? '', 'host.') ? 'host' : 'admin';
    $oldWeekdays = old('weekdays', []);
    $isPermanent = old('is_permanent_weekly', false);
    $rates = $roomType->rates->where('is_active', true);
    if ($rates->isEmpty()) {
        $rates = $roomType->rates;
    }
    $applyToAll = (string) old('apply_to_all_rates', '1') === '1';
    $unifiedDisc = old('discount_percentage', '');
    $unifiedLabel = old('price_label', '');
@endphp

<x-host.can page="room-types.daily-availability" action="write" :panel="$panel">
<form action="{{ $storeRoute }}" method="POST" id="daily-availability-form">
    @csrf
    <div class="mb-3" id="date-range-fields">
        <label class="form-label fw-semibold">از تاریخ <span class="text-danger date-required">*</span></label>
        <input type="text" name="date_from"
               class="form-control @error('date_from') is-invalid @enderror"
               placeholder="{{ $todayJ }}" value="{{ old('date_from', $todayJ) }}"
               autocomplete="off">
        <div class="form-text">تاریخ خورشیدی — مثال: ۱۴۰۵/۰۲/۲۰</div>
        @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3" id="date-to-field">
        <label class="form-label fw-semibold">تا تاریخ <span class="text-danger date-required">*</span></label>
        <input type="text" name="date_to"
               class="form-control @error('date_to') is-invalid @enderror"
               placeholder="{{ $todayJ }}" value="{{ old('date_to', $todayJ) }}"
               autocomplete="off">
        @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold d-block">فیلتر هوشمند — روزهای هفته</label>
        <div class="d-flex flex-wrap gap-2 weekday-chip-group" role="group" aria-label="روزهای هفته">
            @foreach(RoomTypeWeeklyPriceRule::WEEKDAY_LABELS as $iso => $label)
            <input type="checkbox"
                   class="btn-check weekday-cb"
                   name="weekdays[]"
                   value="{{ $iso }}"
                   id="weekday-chip-{{ $iso }}"
                   autocomplete="off"
                   {{ in_array((int) $iso, array_map('intval', (array) $oldWeekdays), true) ? 'checked' : '' }}>
            <label class="btn btn-sm btn-outline-secondary weekday-chip {{ in_array((int) $iso, array_map('intval', (array) $oldWeekdays), true) ? 'active' : '' }}"
                   for="weekday-chip-{{ $iso }}">
                {{ $label }}
            </label>
            @endforeach
        </div>
        <div class="form-text">
            خالی = همه روزها.
            با انتخاب روزها، تنظیمات فقط روی همان روزها در بازه اعمال می‌شود.
        </div>
        @error('weekdays')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="is_permanent_weekly" value="1"
                   id="is_permanent_weekly" {{ $isPermanent ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="is_permanent_weekly">
                قانون دائمی هفتگی
            </label>
        </div>
        <div class="form-text" id="permanent-hint" style="{{ $isPermanent ? '' : 'display:none' }}">
            با فعال‌سازی، قیمت/تخفیف به ازای هر تعرفه برای همیشه روی روزهای انتخابی اعمال می‌شود (بدون نیاز به بازه تاریخ).
            مثال: پنج‌شنبه و جمعه همیشه ۲۰٪ گران‌تر (+۲۰) یا سه‌شنبه همیشه ۵۰٪ تخفیف (−۵۰).
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold">تعداد اتاق موجود <span class="text-danger capacity-required">*</span></label>
        <div class="input-group">
            <input type="number" name="available_count"
                   class="form-control @error('available_count') is-invalid @enderror"
                   min="0" max="{{ $roomType->room_count }}"
                   value="{{ old('available_count', $roomType->room_count) }}">
            <span class="input-group-text">از {{ $roomType->room_count }}</span>
        </div>
        <div class="form-text capacity-hint">صفر = بسته بودن کامل اتاق در آن بازه</div>
        @error('available_count')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    @if($rates->isEmpty())
    <div class="alert alert-warning small mb-3">
        <i class="bi bi-exclamation-triangle me-1"></i>
        ابتدا در صفحه ویرایش اتاق حداقل یک تعرفه تعریف کنید تا بتوانید قیمت‌گذاری روزانه انجام دهید.
    </div>
    @else
    <div class="mb-3" id="price-adjustment-section">
        <input type="hidden" name="apply_to_all_rates" value="0">
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch"
                   name="apply_to_all_rates" value="1" id="apply_to_all_rates"
                   {{ $applyToAll ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="apply_to_all_rates">
                اعمال روی همه تعرفه‌ها
            </label>
        </div>

        <div id="unified-price-fields" style="{{ $applyToAll ? '' : 'display:none' }}">
            <label class="form-label fw-semibold d-block">تغییر قیمت</label>
            <div class="form-text mb-2">درصد تخفیف یا افزایش روی قیمت پایه هر تعرفه اعمال می‌شود. فیلدهای خالی = بدون تغییر.</div>
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label small mb-1">تغییر قیمت %</label>
                    <input type="number" name="discount_percentage"
                           class="form-control form-control-sm unified-disc-input @error('discount_percentage') is-invalid @enderror"
                           min="-100" max="100" placeholder="مثلاً ۵۰ یا −۲۰"
                           value="{{ $unifiedDisc }}">
                    <div class="form-text" style="font-size:.7rem">مثبت = گران‌تر، منفی = تخفیف</div>
                    @error('discount_percentage')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-6">
                    <label class="form-label small mb-1">برچسب قیمت</label>
                    <input type="text" name="price_label"
                           class="form-control form-control-sm unified-label-input @error('price_label') is-invalid @enderror"
                           placeholder="پیک، آخر هفته..."
                           maxlength="60" value="{{ $unifiedLabel }}">
                    @error('price_label')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div id="per-rate-price-fields" style="{{ $applyToAll ? 'display:none' : '' }}">
            <label class="form-label fw-semibold d-block">تغییر قیمت به ازای هر تعرفه</label>
            <div class="form-text mb-2">برای هر تعرفه می‌توانید تخفیف/افزایش یا برچسب جداگانه تنظیم کنید. فیلدهای خالی = بدون تغییر.</div>
            @error('rate_adjustments')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

            <div class="d-flex flex-column gap-2">
                @foreach($rates as $rate)
                @php
                    $rateOld = old('rate_adjustments.' . $rate->id, []);
                    $rateDisc = $rateOld['discount_percentage'] ?? '';
                    $rateLabel = $rateOld['price_label'] ?? '';
                @endphp
                <div class="border rounded-3 p-3 bg-light-subtle rate-adjustment-card" data-rate-id="{{ $rate->id }}">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div>
                            <div class="fw-semibold small">{{ $rate->name }}</div>
                            <div class="text-muted" style="font-size:.75rem">
                                قیمت پایه: {{ \App\Support\PdfPersian::toPersianDigits(number_format($rate->price_per_night, 0, '.', ',')) }} ریال / تخت
                            </div>
                        </div>
                        @if(!$rate->is_active)
                        <span class="badge bg-secondary-subtle text-secondary border">غیرفعال</span>
                        @endif
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1">تغییر قیمت %</label>
                            <input type="number"
                                   name="rate_adjustments[{{ $rate->id }}][discount_percentage]"
                                   class="form-control form-control-sm rate-disc-input @error('rate_adjustments.' . $rate->id . '.discount_percentage') is-invalid @enderror"
                                   min="-100" max="100" placeholder="مثلاً ۵۰ یا −۲۰"
                                   value="{{ $rateDisc }}" {{ $applyToAll ? 'disabled' : '' }}>
                            <div class="form-text" style="font-size:.7rem">مثبت = گران‌تر، منفی = تخفیف</div>
                            @error('rate_adjustments.' . $rate->id . '.discount_percentage')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">برچسب قیمت</label>
                            <input type="text"
                                   name="rate_adjustments[{{ $rate->id }}][price_label]"
                                   class="form-control form-control-sm rate-label-input @error('rate_adjustments.' . $rate->id . '.price_label') is-invalid @enderror"
                                   placeholder="پیک، آخر هفته..."
                                   maxlength="60" value="{{ $rateLabel }}" {{ $applyToAll ? 'disabled' : '' }}>
                            @error('rate_adjustments.' . $rate->id . '.price_label')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="mb-4">
        <label class="form-label fw-semibold">دلیل (اختیاری)</label>
        <input type="text" name="reason" class="form-control"
               placeholder="مثال: تعمیرات، پیک آخر هفته..."
               value="{{ old('reason') }}" maxlength="200">
    </div>
    <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-floppy me-2"></i>ذخیره تنظیمات
    </button>
</form>
</x-host.can>

@push('scripts')
<script>
(function () {
    const form = document.getElementById('daily-availability-form');
    if (!form) return;

    const permanent = form.querySelector('#is_permanent_weekly');
    const dateFields = form.querySelector('#date-range-fields');
    const dateToField = form.querySelector('#date-to-field');
    const permanentHint = form.querySelector('#permanent-hint');
    const dateRequired = form.querySelectorAll('.date-required');
    const capacityRequired = form.querySelector('.capacity-required');
    const capacityHint = form.querySelector('.capacity-hint');
    const dateFrom = form.querySelector('[name=date_from]');
    const dateTo = form.querySelector('[name=date_to]');
    const availCount = form.querySelector('[name=available_count]');

    if (window.BonyadJalaliDate) {
        window.BonyadJalaliDate.bindInput(dateFrom);
        window.BonyadJalaliDate.bindInput(dateTo);
    }

    function togglePermanent() {
        const on = permanent && permanent.checked;
        if (dateFields) dateFields.style.display = on ? 'none' : '';
        if (dateToField) dateToField.style.display = on ? 'none' : '';
        if (permanentHint) permanentHint.style.display = on ? '' : 'none';
        dateRequired.forEach(el => el.style.display = on ? 'none' : '');
        if (dateFrom) dateFrom.required = !on;
        if (dateTo) dateTo.required = !on;
        if (capacityRequired) capacityRequired.style.display = on ? 'none' : '';
        if (availCount) availCount.required = !on;
        if (capacityHint) capacityHint.style.display = on ? 'none' : '';
    }

    if (permanent) {
        permanent.addEventListener('change', togglePermanent);
        togglePermanent();
    }

    form.querySelectorAll('.weekday-cb').forEach(function (cb) {
        var label = cb.id
            ? form.querySelector('label[for="' + cb.id + '"]')
            : cb.closest('label');
        function syncWeekdayChip() {
            if (label) label.classList.toggle('active', cb.checked);
        }
        cb.addEventListener('change', syncWeekdayChip);
        syncWeekdayChip();
    });

    const applyToAllCb = form.querySelector('#apply_to_all_rates');
    const unifiedFields = form.querySelector('#unified-price-fields');
    const perRateFields = form.querySelector('#per-rate-price-fields');
    const unifiedDisc = form.querySelector('.unified-disc-input');
    const unifiedLabel = form.querySelector('.unified-label-input');

    function setPerRateInputsEnabled(enabled) {
        form.querySelectorAll('.rate-disc-input, .rate-label-input').forEach(inp => {
            inp.disabled = !enabled;
        });
    }

    function copyUnifiedToAllRates() {
        const disc = unifiedDisc?.value ?? '';
        const lbl = unifiedLabel?.value ?? '';
        form.querySelectorAll('.rate-adjustment-card').forEach(card => {
            const discEl = card.querySelector('.rate-disc-input');
            const lblEl = card.querySelector('.rate-label-input');
            if (discEl) discEl.value = disc;
            if (lblEl) lblEl.value = lbl;
        });
    }

    function copyFirstRateToUnified() {
        const firstCard = form.querySelector('.rate-adjustment-card');
        if (!firstCard) return;
        if (unifiedDisc) unifiedDisc.value = firstCard.querySelector('.rate-disc-input')?.value ?? '';
        if (unifiedLabel) unifiedLabel.value = firstCard.querySelector('.rate-label-input')?.value ?? '';
    }

    function toggleApplyToAllMode() {
        const on = applyToAllCb?.checked ?? true;
        if (unifiedFields) unifiedFields.style.display = on ? '' : 'none';
        if (perRateFields) perRateFields.style.display = on ? 'none' : '';
        setPerRateInputsEnabled(!on);
        if (on) {
            copyUnifiedToAllRates();
        } else {
            copyFirstRateToUnified();
        }
    }

    if (applyToAllCb) {
        applyToAllCb.addEventListener('change', toggleApplyToAllMode);
        toggleApplyToAllMode();
    }

    [unifiedDisc, unifiedLabel].forEach(el => {
        if (!el) return;
        el.addEventListener('input', function () {
            if (applyToAllCb?.checked) copyUnifiedToAllRates();
        });
    });

    form.addEventListener('submit', function () {
        if (applyToAllCb?.checked) {
            setPerRateInputsEnabled(false);
            copyUnifiedToAllRates();
        }
    });

    window.populateDailyAvailabilityRateFields = function (ratePrices) {
        if (!ratePrices || typeof ratePrices !== 'object') {
            if (unifiedDisc) unifiedDisc.value = '';
            if (unifiedLabel) unifiedLabel.value = '';
            form.querySelectorAll('.rate-disc-input, .rate-label-input').forEach(inp => { inp.value = ''; });
            if (applyToAllCb) {
                applyToAllCb.checked = true;
                toggleApplyToAllMode();
            }
            return;
        }

        const entries = Object.values(ratePrices);
        const discs = entries.map(r => r?.discount_percentage ?? null);
        const labels = entries.map(r => r?.price_label ?? '');
        const allSameDisc = discs.every(d => d === discs[0]);
        const allSameLabel = labels.every(l => l === labels[0]);
        const useUnified = entries.length <= 1 || (allSameDisc && allSameLabel);

        if (useUnified) {
            const sample = entries[0] || {};
            if (applyToAllCb) applyToAllCb.checked = true;
            if (unifiedDisc) unifiedDisc.value = sample.discount_percentage != null ? sample.discount_percentage : '';
            if (unifiedLabel) unifiedLabel.value = sample.price_label || '';
            toggleApplyToAllMode();
            return;
        }

        if (applyToAllCb) applyToAllCb.checked = false;
        toggleApplyToAllMode();
        form.querySelectorAll('.rate-adjustment-card').forEach(card => {
            const rateId = card.dataset.rateId;
            const data = ratePrices[rateId] || ratePrices[String(rateId)] || null;
            const discEl = card.querySelector('.rate-disc-input');
            const lblEl = card.querySelector('.rate-label-input');
            if (discEl) discEl.value = data && data.discount_percentage != null ? data.discount_percentage : '';
            if (lblEl) lblEl.value = data && data.price_label ? data.price_label : '';
        });
    };
})();
</script>
@endpush
