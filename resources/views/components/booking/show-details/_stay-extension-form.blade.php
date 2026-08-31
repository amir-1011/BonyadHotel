@php
    $canExtend = $canExtendStay ?? false;
    $isProgramBooking = $booking->isProgram();
    $isMedical = $booking->isMedicalAccommodation();
    $canShorten = $isMedical && ($booking->canShortenStay(auth()->user()) ?? false);
@endphp

@if($canExtend)
<div class="border-top mt-3 pt-3" data-stay-extension-form>
    <div class="fw-semibold mb-2">
        <i class="bi bi-calendar-plus me-1 text-primary"></i>
        {{ $isMedical ? 'تغییر تاریخ پایان (تمدید یا کاهش بدون جریمه)' : 'تمدید تاریخ پایان' }}
    </div>
    <p class="text-muted small mb-2">
        تاریخ پایان فعلی: <strong dir="ltr">@jalali($booking->check_out)</strong>
        · {{ $booking->nights }} شب
        @if($isProgramBooking)
        <br><span class="text-warning">برنامه/اردو: مبالغ مالی به‌صورت دستی ثبت شده‌اند و پس از تمدید خودکار به‌روز نمی‌شوند.</span>
        @elseif($isMedical)
        <br>اسکان درمانی: مبلغ فاکتور بر اساس تعرفه شبانه بیمه دی به‌روز می‌شود و سیاست کنسلی اعمال نمی‌گردد.
        @else
        <br>پس از تمدید، مبلغ اقامت و خدمات بر اساس نرخ روزانه محاسبه می‌شود.
        @endif
    </p>

    <div class="d-flex flex-wrap gap-2 mb-2">
        @if($canShorten)
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.bnbStayExtensionAddNights?.(this, -1)">−۱ شب</button>
        @endif
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.bnbStayExtensionAddNights?.(this, 1)">+۱ شب</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.bnbStayExtensionAddNights?.(this, 3)">+۳ شب</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.bnbStayExtensionAddNights?.(this, 7)">+۷ شب</button>
    </div>

    <div class="row g-2 align-items-end">
        <div class="col-sm-8">
            <label class="form-label small mb-1">تاریخ پایان جدید (شمسی)</label>
            <input type="text"
                   class="form-control form-control-sm @error('extendCheckOutJalali') is-invalid @enderror"
                   wire:model="extendCheckOutJalali"
                   data-stay-extension-input
                   data-default-checkout="{{ \Morilog\Jalali\Jalalian::fromCarbon($booking->check_out)->format('Y/m/d') }}"
                   dir="ltr"
                   placeholder="۱۴۰۴/۰۸/۱۵"
                   autocomplete="off">
            @error('extendCheckOutJalali')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-sm-4">
            <button type="button"
                    class="btn btn-sm btn-primary w-100"
                    data-bnb-price-change
                    data-bnb-price-action="extendStayCheckout"
                    data-bnb-price-params="{}"
                    wire:loading.attr="disabled"
                    wire:target="executeConfirmedPriceChange,previewBookingPriceChange,extendStayCheckout">
                <span wire:loading.remove wire:target="executeConfirmedPriceChange,previewBookingPriceChange,extendStayCheckout"><i class="bi bi-check-lg me-1"></i>{{ $isMedical ? 'ثبت تغییر تاریخ' : 'ثبت تمدید' }}</span>
                <span wire:loading wire:target="executeConfirmedPriceChange,previewBookingPriceChange,extendStayCheckout" class="spinner-border spinner-border-sm"></span>
            </button>
        </div>
    </div>
</div>
@endif
