@if($canModifyBookingRooms ?? false)
@php
    $roomTypeOptions = $this->addRoomTypeOptions();
    $rateOptions = $this->addRoomRateOptions();
    $isProgramBooking = $booking->isProgram();
@endphp
<div class="border-top mt-3 pt-3" data-add-room-form>
    <div class="fw-semibold mb-2"><i class="bi bi-plus-square me-1 text-primary"></i>افزودن اتاق به رزرو</div>
    <p class="text-muted small mb-2">
        بازه اقامت: <strong dir="ltr">@jalali($booking->check_in)</strong>
        → <strong dir="ltr">@jalali($booking->check_out)</strong>
        @if($isProgramBooking)
        <br><span class="text-warning">برنامه/اردو: مبالغ مالی خودکار به‌روز نمی‌شوند.</span>
        @else
        <br>پس از افزودن اتاق، مبلغ اقامت مجدداً محاسبه می‌شود.
        @endif
    </p>

    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label small mb-1">نوع اتاق</label>
            <select wire:model.live="addRoomRoomTypeId" class="form-select form-select-sm @error('addRoomRoomTypeId') is-invalid @enderror">
                <option value="">— انتخاب نوع —</option>
                @foreach($roomTypeOptions as $type)
                <option value="{{ $type->id }}">{{ $type->name }} · ظرفیت {{ $type->capacity }}</option>
                @endforeach
            </select>
            @error('addRoomRoomTypeId')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-1">تعرفه</label>
            <select wire:model="addRoomRoomRateId" class="form-select form-select-sm @error('addRoomRoomRateId') is-invalid @enderror" @disabled($addRoomRoomTypeId === '')>
                <option value="">— پیش‌فرض —</option>
                @foreach($rateOptions as $rate)
                <option value="{{ $rate->id }}">{{ $rate->name }} · {{ \App\Support\PdfPersian::toPersianDigits(number_format($rate->price_per_night)) }} ریال/شب</option>
                @endforeach
            </select>
            @error('addRoomRoomRateId')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-4 col-md-3">
            <label class="form-label small mb-1">بزرگسال</label>
            <input type="number" min="1" max="20" wire:model="addRoomAdults" class="form-control form-control-sm">
        </div>
        <div class="col-4 col-md-3">
            <label class="form-label small mb-1">کودک &lt;۶</label>
            <input type="number" min="0" max="19" wire:model="addRoomChildrenUnder6" class="form-control form-control-sm">
        </div>
        <div class="col-4 col-md-3">
            <label class="form-label small mb-1">کف‌خواب</label>
            <input type="number" min="0" max="10" wire:model="addRoomExtraGuests" class="form-control form-control-sm">
        </div>
        <div class="col-12 col-md-3 d-flex align-items-end">
            <div class="form-check mb-2">
                <input type="checkbox" wire:model="addRoomBillFullRooms" class="form-check-input" id="add-room-full-{{ $booking->id }}">
                <label class="form-check-label small" for="add-room-full-{{ $booking->id }}">رزرو کامل اتاق</label>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
        <button type="button"
                class="btn btn-sm btn-outline-secondary"
                wire:click="openAddRoomPicker"
                wire:loading.attr="disabled"
                wire:target="openAddRoomPicker,commitAddRoomLine"
                @disabled($addRoomRoomTypeId === '')>
            <i class="bi bi-door-open me-1"></i>انتخاب اتاق فیزیکی
        </button>
        @if($addRoomPhysicalRoomId)
        <span class="badge text-bg-dark">{{ $addRoomPhysicalRoomName ?: ('#' . $addRoomPhysicalRoomId) }}</span>
        <button type="button" wire:click="clearAddRoomPhysicalSelection" class="btn btn-sm btn-link text-danger p-0">حذف</button>
        @endif
        <button type="button"
                class="btn btn-sm btn-primary ms-auto"
                data-bnb-price-change
                data-bnb-price-action="commitAddRoomLine"
                data-bnb-price-params="{}"
                wire:loading.attr="disabled"
                wire:target="executeConfirmedPriceChange,previewBookingPriceChange,commitAddRoomLine,openAddRoomPicker">
            <span wire:loading.remove wire:target="executeConfirmedPriceChange,previewBookingPriceChange,commitAddRoomLine"><i class="bi bi-check-lg me-1"></i>ثبت اتاق</span>
            <span wire:loading wire:target="executeConfirmedPriceChange,previewBookingPriceChange,commitAddRoomLine" class="spinner-border spinner-border-sm"></span>
        </button>
    </div>
</div>
@endif
