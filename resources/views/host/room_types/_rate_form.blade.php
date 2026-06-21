@php
$rName         = old('name',                       $rate?->name ?? '');
$rPrice        = old('price_per_night',            $rate?->price_per_night ?? '');
$rBreakfast    = old('breakfast_included',         $rate?->breakfast_included ?? false);
$rBreakfastP   = old('breakfast_price_per_person', $rate?->breakfast_price_per_person ?? '');
$rCancellation = old('cancellation_policy',       $rate?->cancellation_policy ?? 'free');
$rPayment      = old('payment_type',               $rate?->payment_type ?? 'pay_at_hotel');
$rActive       = old('is_active',                  $rate?->is_active ?? true);
$rateId        = $rate?->id ?? 'new';
@endphp

@once
@push('styles')
<style>
.rate-form .form-label { margin-bottom: .35rem; }
.rate-form-status-box {
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: .375rem;
    padding: .4rem .75rem;
    min-height: 38px;
    display: flex;
    align-items: center;
}
.rate-form-breakfast {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: .5rem;
    overflow: hidden;
}
.rate-form-breakfast-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: .65rem 1rem;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}
.rate-form-breakfast--free .rate-form-breakfast-header {
    border-bottom: none;
}
.rate-form-breakfast-title {
    display: flex;
    align-items: center;
    gap: .4rem;
    font-weight: 600;
    font-size: .9rem;
    color: #495057;
}
.rate-form-breakfast-body {
    padding: .75rem 1rem;
}
.rate-form-breakfast-hint {
    display: flex;
    align-items: flex-start;
    gap: .35rem;
    margin-top: .4rem;
    font-size: .78rem;
    color: #6c757d;
    line-height: 1.5;
}
.rate-form-breakfast-hint .bi { margin-top: .15rem; flex-shrink: 0; }
.rate-form-price-hint {
    display: flex;
    align-items: center;
    gap: .3rem;
    margin-top: .35rem;
    font-size: .78rem;
    color: #198754;
}
</style>
@endpush
@endonce

<div class="rate-form">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">نام تعرفه <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ $rName }}" placeholder="مثلاً: بدون صبحانه، با صبحانه">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold">قیمت هر شب (تومان) <span class="text-danger">*</span></label>
            <input type="number" name="price_per_night" class="form-control @error('price_per_night') is-invalid @enderror"
                   value="{{ $rPrice }}" min="1" placeholder="مثلاً: 1500000">
            @error('price_per_night')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="rate-form-price-hint">
                <i class="bi bi-check-circle-fill"></i>
                مبنای محاسبه مبلغ رزرو
            </div>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">سیاست لغو</label>
            <select name="cancellation_policy" class="form-select">
                <option value="free" @selected($rCancellation === 'free')>لغو رایگان</option>
                <option value="non_refundable" @selected($rCancellation === 'non_refundable')>غیر قابل استرداد</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">نوع پرداخت</label>
            <select name="payment_type" class="form-select">
                <option value="pay_at_hotel" @selected($rPayment === 'pay_at_hotel')>پرداخت در محل</option>
                <option value="prepay_online" @selected($rPayment === 'prepay_online')>پرداخت آنلاین</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-semibold">وضعیت تعرفه</label>
            <div class="rate-form-status-box">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1"
                           id="rActive{{ $rateId }}" @checked($rActive)>
                    <label class="form-check-label" for="rActive{{ $rateId }}">فعال</label>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="rate-form-breakfast {{ $rBreakfast ? 'rate-form-breakfast--free' : '' }}" id="bfBox{{ $rateId }}">
                <div class="rate-form-breakfast-header">
                    <span class="rate-form-breakfast-title">
                        <i class="bi bi-cup-hot text-warning"></i> صبحانه
                    </span>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="breakfast_included" value="1"
                               id="bfInc{{ $rateId }}" @checked($rBreakfast)
                               onchange="toggleBfPrice(this, '{{ $rateId }}')">
                        <label class="form-check-label" for="bfInc{{ $rateId }}">صبحانه رایگان</label>
                    </div>
                </div>
                {{-- <div class="rate-form-breakfast-body" id="bfPriceWrap{{ $rateId }}" @if($rBreakfast) style="display:none" @endif>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5 col-lg-4">
                            <label class="form-label mb-1">قیمت صبحانه (تومان/نفر)</label>
                            <input type="number" name="breakfast_price_per_person" class="form-control form-control-sm"
                                   value="{{ $rBreakfastP }}" min="0" placeholder="خالی = بدون صبحانه">
                        </div>
                    </div>
                    <div class="rate-form-breakfast-hint">
                        <i class="bi bi-info-circle"></i>
                        <span>فقط برای نمایش به مهمان — مبلغ رزرو از «قیمت هر شب» محاسبه می‌شود.</span>
                    </div>
                </div> --}}
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function toggleBfPrice(el, id) {
    const wrap = document.getElementById('bfPriceWrap' + id);
    const box  = document.getElementById('bfBox' + id);
    if (wrap) wrap.style.display = el.checked ? 'none' : '';
    if (box)  box.classList.toggle('rate-form-breakfast--free', el.checked);
}
</script>
@endpush
@endonce
