<div>
@php
$rName        = old('name',                        $rate?->name ?? '');
$rPrice       = old('price_per_night',             $rate?->price_per_night ?? '');
$rBreakfast   = old('breakfast_included',          $rate?->breakfast_included ?? false);
$rBreakfastP  = old('breakfast_price_per_person',  $rate?->breakfast_price_per_person ?? '');
$rCancellation = old('cancellation_policy',        $rate?->cancellation_policy ?? 'free');
$rPayment     = old('payment_type',                $rate?->payment_type ?? 'pay_at_hotel');
$rActive      = old('is_active',                   $rate?->is_active ?? true);
@endphp

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
    </div>

    <div class="col-md-4">
        <div class="form-check form-switch mt-3">
            <input class="form-check-input" type="checkbox" name="breakfast_included" value="1"
                   id="bfInc{{ $rate?->id }}" @checked($rBreakfast)
                   onchange="toggleBfPrice(this, '{{ $rate?->id }}')">
            <label class="form-check-label" for="bfInc{{ $rate?->id }}">صبحانه رایگان</label>
        </div>
    </div>
    <div class="col-md-4" id="bfPriceWrap{{ $rate?->id }}" style="{{ $rBreakfast ? 'display:none' : '' }}">
        <label class="form-label fw-semibold">قیمت صبحانه (تومان/نفر)</label>
        <input type="number" name="breakfast_price_per_person" class="form-control"
               value="{{ $rBreakfastP }}" min="0" placeholder="خالی = بدون صبحانه">
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
        <div class="form-check form-switch mt-3">
            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                   id="rActive{{ $rate?->id }}" @checked($rActive)>
            <label class="form-check-label" for="rActive{{ $rate?->id }}">فعال</label>
        </div>
    </div>
</div>

<script>
function toggleBfPrice(el, id) {
    const wrap = document.getElementById('bfPriceWrap' + id);
    if (wrap) wrap.style.display = el.checked ? 'none' : '';
}
</script>

</div>