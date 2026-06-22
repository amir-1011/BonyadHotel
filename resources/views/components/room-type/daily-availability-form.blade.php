@props([
    'roomType',
    'storeRoute',
    'weeklyDestroyRoutePrefix' => null,
])

@php
    use App\Models\RoomTypeWeeklyPriceRule;
    $todayJ = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::today())->format('Y/m/d');
    $oldWeekdays = old('weekdays', []);
    $isPermanent = old('is_permanent_weekly', false);
@endphp

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
        <div class="d-flex flex-wrap gap-2">
            @foreach(RoomTypeWeeklyPriceRule::WEEKDAY_LABELS as $iso => $label)
            <label class="btn btn-sm btn-outline-secondary {{ in_array($iso, $oldWeekdays) ? 'active' : '' }}">
                <input type="checkbox" name="weekdays[]" value="{{ $iso }}" class="d-none weekday-cb"
                       {{ in_array($iso, $oldWeekdays) ? 'checked' : '' }}>
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
            با فعال‌سازی، قیمت/تخفیف برای همیشه روی روزهای انتخابی اعمال می‌شود (بدون نیاز به بازه تاریخ).
            مثال: پنج‌شنبه و جمعه همیشه ۲۰٪ گران‌تر (−۲۰) یا سه‌شنبه همیشه ۵۰٪ تخفیف.
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
    <div class="mb-3">
        <label class="form-label fw-semibold">قیمت سفارشی شب (اختیاری)</label>
        <div class="input-group">
            <x-money-input name="custom_price"
                   class="form-control @error('custom_price') is-invalid @enderror"
                   placeholder="خالی = قیمت پایه تعریف‌شده"
                   value="{{ old('custom_price') }}" />
            <span class="input-group-text">تومان</span>
        </div>
        @error('custom_price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div class="row g-2 mb-3">
        <div class="col-6">
            <label class="form-label fw-semibold">تغییر قیمت %</label>
            <input type="number" name="discount_percentage"
                   class="form-control @error('discount_percentage') is-invalid @enderror"
                   min="-100" max="100" placeholder="مثلاً ۵۰ یا −۲۰"
                   value="{{ old('discount_percentage') }}">
            <div class="form-text">مثبت = تخفیف، منفی = گران‌تر</div>
            @error('discount_percentage')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-6">
            <label class="form-label fw-semibold">برچسب قیمت</label>
            <input type="text" name="price_label"
                   class="form-control @error('price_label') is-invalid @enderror"
                   placeholder="پیک، آخر هفته، تخفیف ویژه..."
                   maxlength="60" value="{{ old('price_label') }}">
        </div>
    </div>
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

    form.querySelectorAll('.weekday-cb').forEach(cb => {
        cb.addEventListener('change', function () {
            this.closest('label').classList.toggle('active', this.checked);
        });
    });
})();
</script>
@endpush
