@props(['rate' => null])

@php
$rName         = old('name',                       $rate?->name ?? '');
$rPrice        = old('price_per_night',            $rate?->price_per_night ?? '');
$rBreakfast    = old('breakfast_included',         $rate?->breakfast_included ?? false);
$rCancellation = old('cancellation_policy',       $rate?->cancellation_policy ?? 'free');
$rPayment      = old('payment_type',               $rate?->payment_type ?? 'pay_at_hotel');
$rActive       = old('is_active',                  $rate?->is_active ?? true);
$rateId        = $rate?->id ?? 'new';
@endphp

<div class="rt-rate-form">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">نام تعرفه <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ $rName }}" placeholder="مثلاً: بدون صبحانه، با صبحانه">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">قیمت هر شب (تومان) <span class="text-danger">*</span></label>
            <x-money-input name="price_per_night" class="form-control @error('price_per_night') is-invalid @enderror"
                   value="{{ $rPrice }}" placeholder="مثلاً: 1,500,000" />
            @error('price_per_night')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text"><i class="bi bi-info-circle me-1"></i>مبنای محاسبه مبلغ رزرو</div>
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
            <label class="rt-rate-option rt-rate-option--wide">
                <input type="checkbox" name="is_active" value="1"
                       class="rt-rate-option-input" id="rActive{{ $rateId }}" @checked($rActive)>
                <span class="rt-rate-option-body">
                    <i class="bi bi-toggle-on text-primary"></i>
                    <span>فعال — نمایش در سایت</span>
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
function toggleBfTile(el, id) {
    const tile = document.getElementById('bfTile' + id);
    if (tile) tile.classList.toggle('rt-rate-breakfast-tile--on', el.checked);
}
</script>
@endpush
@endonce
