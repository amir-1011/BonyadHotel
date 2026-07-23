@props([
    'rate' => null,
    'formScope' => 'create',
    'formRateId' => null,
])

@php
$activeScope = old('rate_form_scope');
$activeRateId = (int) old('rate_id', 0);
$isSubmittedForm = $activeScope === $formScope
    && ($formScope === 'create' || (int) $formRateId === $activeRateId);

$fromOld = fn (string $key, mixed $default) => $isSubmittedForm ? old($key, $default) : $default;

$rName         = $fromOld('rate_name', $rate?->name ?? '');
$rPrice        = $fromOld('price_per_night', $rate?->price_per_night ?? '');
$rCancellation = $fromOld('cancellation_policy', $rate?->cancellation_policy ?? 'free');
$rPayment      = $fromOld('payment_type', $rate?->payment_type ?? 'pay_at_hotel');

if ($isSubmittedForm) {
    $rBreakfast = old('breakfast_included') !== null ? (bool) old('breakfast_included') : (bool) ($rate?->breakfast_included ?? false);
    $rActive    = old('is_active') !== null ? (bool) old('is_active') : (bool) ($rate?->is_active ?? true);
} else {
    $rBreakfast = (bool) ($rate?->breakfast_included ?? false);
    $rActive    = (bool) ($rate?->is_active ?? true);
}

$rateId = $rate?->id ?? 'new';
@endphp

<div class="rt-rate-form">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">نام تعرفه <span class="text-danger">*</span></label>
            <input type="text" name="rate_name"
                   class="form-control {{ $isSubmittedForm && $errors->has('rate_name') ? 'is-invalid' : '' }}"
                   value="{{ $rName }}" placeholder="مثلاً: بدون صبحانه، با صبحانه">
            @if($isSubmittedForm)
                @error('rate_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @endif
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">قیمت هر شب به ازای هر تخت (تومان) <span class="text-danger">*</span></label>
            <x-money-input name="price_per_night"
                   class="form-control {{ $isSubmittedForm && $errors->has('price_per_night') ? 'is-invalid' : '' }}"
                   value="{{ $rPrice }}" placeholder="مثلاً: 1,500,000" />
            @if($isSubmittedForm)
                @error('price_per_night')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @endif
            <div class="form-text"><i class="bi bi-info-circle me-1"></i>این مبلغ به ازای هر تخت در هر شب است، نه برای کل اتاق.</div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold d-block mb-2">سیاست لغو</label>
            <div class="rt-rate-options">
                <label class="rt-rate-option">
                    <input type="radio" name="cancellation_policy" value="free"
                           class="rt-rate-option-input" @checked($rCancellation === 'free')>
                    <span class="rt-rate-option-body">
                        <i class="bi bi-arrow-counterclockwise text-success"></i>
                        <span>لغو رایگان</span>
                    </span>
                </label>
                <label class="rt-rate-option">
                    <input type="radio" name="cancellation_policy" value="non_refundable"
                           class="rt-rate-option-input" @checked($rCancellation === 'non_refundable')>
                    <span class="rt-rate-option-body">
                        <i class="bi bi-x-circle text-danger"></i>
                        <span>غیر قابل استرداد</span>
                    </span>
                </label>
            </div>
        </div>

        <input type="hidden" name="payment_type" value="{{ $rPayment }}">

        <div class="col-md-6">
            <label class="form-label fw-semibold d-block mb-2">وضعیت تعرفه</label>
            <label class="rt-rate-option rt-rate-option--wide rt-rate-active-toggle {{ $rActive ? 'rt-rate-active-toggle--on' : 'rt-rate-active-toggle--off' }}">
                <input type="checkbox" name="is_active" value="1"
                       class="rt-rate-option-input rt-rate-active-toggle__input" id="rActive{{ $rateId }}"
                       @checked($rActive) onchange="syncRateActiveToggle(this)">
                <span class="rt-rate-option-body">
                    <i class="bi {{ $rActive ? 'bi-toggle-on text-primary' : 'bi-toggle-off text-secondary' }} rt-rate-active-toggle__icon"></i>
                    <span class="rt-rate-active-toggle__text">{{ $rActive ? 'فعال — نمایش در سایت' : 'غیرفعال — مخفی از سایت' }}</span>
                </span>
            </label>
        </div>

        <div class="col-12">
            <label class="rt-rate-breakfast-tile {{ $rBreakfast ? 'rt-rate-breakfast-tile--on' : '' }}" id="bfTile{{ $rateId }}">
                <input type="checkbox" name="breakfast_included" value="1"
                       class="rt-rate-option-input" id="bfInc{{ $rateId }}" @checked($rBreakfast)
                       onchange="toggleBfTile(this, '{{ $rateId }}')">
                <span class="rt-rate-breakfast-tile__body">
                    <span class="rt-rate-breakfast-tile__icon"><i class="bi bi-cup-hot"></i></span>
                    <span>
                        <span class="rt-rate-breakfast-tile__title">صبحانه رایگان</span>
                        <span class="rt-rate-breakfast-tile__hint">با فعال بودن، این تعرفه شامل صبحانه رایگان است</span>
                    </span>
                    <i class="bi bi-check-circle-fill rt-rate-breakfast-tile__check" aria-hidden="true"></i>
                </span>
            </label>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function syncRateActiveToggle(el) {
    const toggle = el.closest('.rt-rate-active-toggle');
    if (!toggle) return;

    const isOn = el.checked;
    const icon = toggle.querySelector('.rt-rate-active-toggle__icon');
    const text = toggle.querySelector('.rt-rate-active-toggle__text');

    toggle.classList.toggle('rt-rate-active-toggle--on', isOn);
    toggle.classList.toggle('rt-rate-active-toggle--off', !isOn);

    if (icon) {
        icon.className = 'rt-rate-active-toggle__icon bi ' + (isOn ? 'bi-toggle-on text-primary' : 'bi-toggle-off text-secondary');
    }
    if (text) {
        text.textContent = isOn ? 'فعال — نمایش در سایت' : 'غیرفعال — مخفی از سایت';
    }
}

function toggleBfTile(el, id) {
    const tile = document.getElementById('bfTile' + id);
    if (tile) tile.classList.toggle('rt-rate-breakfast-tile--on', el.checked);
}
</script>
@endpush
@endonce
