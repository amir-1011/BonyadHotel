@if($canModifyBookingRooms ?? false)
@php
    $roomOptions = $this->guestAdditionRoomOptions();
    $isProgramBooking = $booking->isProgram();
@endphp
@if(!empty($roomOptions))
<div class="border-top mt-3 pt-3" data-add-guest-form>
    <div class="fw-semibold mb-2"><i class="bi bi-person-plus me-1 text-primary"></i>افزودن نفر به اتاق فروخته‌شده</div>
    <p class="text-muted small mb-2">
        @if($isProgramBooking)
        <span class="text-warning">برنامه/اردو: مبالغ مالی خودکار به‌روز نمی‌شوند.</span>
        @else
        با افزودن نفر، مبلغ اقامت (و در صورت نیاز کف‌خواب) مجدداً محاسبه می‌شود.
        @endif
    </p>
    <div class="d-flex flex-column gap-2">
        @foreach($roomOptions as $roomOption)
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded px-2 py-2 bg-light-subtle">
            <div class="small">
                <strong>{{ $roomOption['label'] }}</strong>
                <span class="text-muted ms-1">{{ $roomOption['guests'] }} / {{ $roomOption['capacity'] }} نفر</span>
            </div>
            @if($roomOption['can_add_guest'])
            <button type="button"
                    class="btn btn-sm btn-outline-primary"
                    data-bnb-price-change
                    data-bnb-price-action="addGuestToSoldRoom"
                    data-bnb-price-params='@json(["bookingRoomId" => $roomOption["id"]])'
                    wire:loading.attr="disabled"
                    wire:target="executeConfirmedPriceChange,previewBookingPriceChange,addGuestToSoldRoom">
                <span wire:loading.remove wire:target="executeConfirmedPriceChange,previewBookingPriceChange,addGuestToSoldRoom({{ $roomOption['id'] }})"><i class="bi bi-plus-lg me-1"></i>افزودن نفر</span>
                <span wire:loading wire:target="executeConfirmedPriceChange,previewBookingPriceChange,addGuestToSoldRoom({{ $roomOption['id'] }})" class="spinner-border spinner-border-sm"></span>
            </button>
            @else
            <span class="badge text-bg-secondary">ظرفیت تکمیل</span>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif
@endif
