{{-- Physical room picker modal for manual booking.
     Teleport to body so position:fixed is viewport-relative. Parent .card
     uses contain:layout, which otherwise traps the overlay at the card top. --}}
<div x-data="bnbRoomPicker()"
     @manual-booking-open-room-picker.window="openPicker($event.detail)">
<template x-teleport="body">
<div id="bnb-room-picker-modal"
     x-show="open"
     x-cloak
     style="position:fixed;inset:0;z-index:1080;display:flex;align-items:center;justify-content:center;padding:16px;">
    <div style="position:absolute;inset:0;background:rgba(0,0,0,.5);" @click="close()"></div>

    <div class="bg-white rounded-3 shadow-lg position-relative"
         style="width:100%;max-width:720px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;z-index:1;"
         @click.stop>
        <div class="p-3 border-bottom d-flex justify-content-between align-items-start gap-2">
            <div>
                <h6 class="fw-bold mb-1"><i class="bi bi-door-open me-1"></i>انتخاب اتاق مشخص</h6>
                <div class="text-muted small">
                    <span x-text="roomTypeName"></span>
                    <span x-show="checkIn" dir="ltr">
                        <span x-show="roomTypeName"> · </span>
                        <span x-text="jalaliStr(checkIn)"></span> → <span x-text="jalaliStr(checkOut)"></span>
                    </span>
                    <span x-show="roomsToSelect > 1" x-text="needLabel"></span>
                </div>
                <div class="small fw-semibold text-primary mt-1" x-show="roomsToSelect > 1" x-text="selectionLabel"></div>
            </div>
            <button type="button" class="btn-close" @click="close()"></button>
        </div>

        <div class="p-3 overflow-auto flex-grow-1">
            <div x-show="loading" class="text-center py-4 text-muted">
                <div class="spinner-border spinner-border-sm me-2"></div>در حال بارگذاری اتاق‌ها...
            </div>

            <div x-show="error && !loading" class="alert alert-danger small" x-text="error"></div>

            <div x-show="!loading && !error && rooms.length === 0" class="alert alert-warning small mb-0">
                اتاق فیزیکی برای این نوع تعریف نشده. لطفاً از بخش مدیریت اتاق‌ها، اتاق‌های فیزیکی را تعریف کنید.
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3" x-show="!loading">
                <span class="badge bg-success-subtle text-success border border-success-subtle">آزاد</span>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">بسته (سیاست قیمتی)</span>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">مسدود</span>
                <span class="badge bg-secondary-subtle text-secondary border">رزرو شده</span>
            </div>

            <div class="row g-3" x-show="!loading && rooms.length">
                <template x-for="room in rooms" :key="room.id">
                    <div class="col-12 col-sm-6 col-lg-4">
                        <button type="button"
                                class="physical-room-pick-card w-100 text-start"
                                :class="[
                                    'physical-room-pick-card--' + room.color,
                                    isSelected(room.id) ? 'physical-room-pick-card--picked' : ''
                                ]"
                                :disabled="!room.selectable && !isSelected(room.id)"
                                @click="toggleRoom(room)">
                            <div class="d-flex justify-content-between align-items-start gap-1 mb-2">
                                <strong class="small" x-text="room.name"></strong>
                                <span class="badge rounded-pill"
                                      :class="'text-bg-' + room.color"
                                      style="font-size:.65rem;"
                                      x-text="room.status_label"></span>
                            </div>
                            <p class="text-muted mb-2 small" style="font-size:.75rem;line-height:1.5;"
                               x-show="room.description" x-text="room.description"></p>
                            <div class="small text-secondary mb-2" x-show="room.room_type_name" x-text="room.room_type_name"></div>
                            <div class="d-flex flex-wrap gap-1" x-show="room.amenities && room.amenities.length">
                                <template x-for="a in room.amenities.slice(0, 4)" :key="a">
                                    <span class="badge bg-light text-dark border" style="font-size:.65rem;" x-text="a"></span>
                                </template>
                            </div>
                        </button>
                    </div>
                </template>
            </div>
        </div>

        <div class="p-3 border-top bg-light d-flex justify-content-between align-items-center gap-2">
            <span class="small text-muted" x-show="roomsToSelect > 1" x-text="selectionLabel"></span>
            <div class="d-flex gap-2 ms-auto">
                <button type="button" class="btn btn-outline-secondary btn-sm" @click="close()">انصراف</button>
                <button type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="!canConfirm"
                        @click="confirmSelection()">
                    <span x-text="confirmLabel"></span>
                </button>
            </div>
        </div>
    </div>
</div>
</template>
</div>

@once
@push('styles')
<style>
.physical-room-pick-card {
    border: 2px solid var(--bs-border-color);
    border-radius: .75rem;
    padding: .85rem;
    background: var(--bs-body-bg);
    transition: transform .12s, box-shadow .12s, border-color .12s;
    min-height: 110px;
}
.physical-room-pick-card:not(:disabled):hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
}
.physical-room-pick-card:disabled {
    opacity: .72;
    cursor: not-allowed;
}
.physical-room-pick-card--success {
    border-color: rgba(var(--bs-success-rgb), .45);
    background: rgba(var(--bs-success-rgb), .06);
}
.physical-room-pick-card--warning {
    border-color: rgba(var(--bs-warning-rgb), .5);
    background: rgba(var(--bs-warning-rgb), .08);
    background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(var(--bs-warning-rgb), .08) 4px, rgba(var(--bs-warning-rgb), .08) 5px);
}
.physical-room-pick-card--danger {
    border-color: rgba(var(--bs-danger-rgb), .45);
    background: rgba(var(--bs-danger-rgb), .06);
    background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(var(--bs-danger-rgb), .1) 4px, rgba(var(--bs-danger-rgb), .1) 5px);
}
.physical-room-pick-card--secondary {
    border-color: var(--bs-border-color);
    background: #f8f9fa;
    background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(0,0,0,.05) 4px, rgba(0,0,0,.05) 5px);
}
.physical-room-pick-card--info {
    border-color: rgba(var(--bs-info-rgb), .45);
    background: rgba(var(--bs-info-rgb), .06);
}
.physical-room-pick-card--picked {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), .2);
    background: rgba(var(--bs-primary-rgb), .08);
}
</style>
@endpush
@endonce
