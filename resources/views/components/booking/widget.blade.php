@props([
    'accommodation',
    'roomTypes' => null,
    'mode' => 'manual',
    'defaultDiscountPct' => 0,
    'prefillRoomTypeId' => null,
    'prefillRoomRateId' => null,
    'prefillRoomId' => null,
    'prefillRoomName' => null,
    'prefillFocusDates' => false,
])
@php
    $roomTypes = $roomTypes ?? $accommodation->roomTypes;
    $hasPrefill = $mode === 'manual' && $prefillRoomTypeId && $prefillRoomRateId;
    $prefillRt = $hasPrefill ? $roomTypes->firstWhere('id', $prefillRoomTypeId) : null;
    $prefillRate = $prefillRt ? $prefillRt->rates->firstWhere('id', $prefillRoomRateId) : null;
    $hasPrefill = $hasPrefill && $prefillRt && $prefillRate;
    $prefillDiscPrice = $hasPrefill && $defaultDiscountPct > 0
        ? (int) round($prefillRate->price_per_night * (1 - $defaultDiscountPct / 100))
        : (int) $prefillRate?->price_per_night;
@endphp

@include('components.booking.assets', [
    'accommodationId' => $accommodation->id,
    'defaultDiscountPct' => $defaultDiscountPct,
    'childAllocateBed' => $accommodation->childrenUnder6AllocateBed(),
    'childDiscountPct' => $accommodation->childrenUnder6DiscountPercentage(),
])

@if($hasPrefill)
<div id="manual-booking-prefill-summary" class="alert alert-primary border border-primary-subtle mb-3 py-3">
    <div class="d-flex align-items-start gap-2">
        <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
        <div class="small">
            <div class="fw-bold mb-1">اتاق و تعرفه از قبل انتخاب شده‌اند</div>
            <div>
                <span class="badge bg-white text-primary border border-primary-subtle">{{ $prefillRt->name }}</span>
                <span class="badge bg-white text-dark border">{{ $prefillRate->name }}</span>
                @if($prefillRoomName)
                <span class="badge bg-white text-dark border"><i class="bi bi-door-open me-1"></i>{{ $prefillRoomName }}</span>
                @endif
            </div>
            <div class="text-primary-emphasis mt-2 mb-0">
                <i class="bi bi-arrow-down me-1"></i>تاریخ ورود و خروج و تعداد نفرات را در بخش زیر انتخاب کنید.
            </div>
        </div>
    </div>
</div>

@include('components.booking.quick-book-drawer', [
    'mode' => $mode,
    'defaultDiscountPct' => $defaultDiscountPct,
    'accommodationEditUrl' => route(request()->routeIs('admin.*') ? 'admin.accommodations.edit' : 'host.accommodations.edit', $accommodation),
    'prefillRoomTypeId' => $prefillRoomTypeId,
    'prefillRoomRateId' => $prefillRoomRateId,
    'prefillRoomId' => $prefillRoomId,
    'prefillRoomName' => $prefillRoomName,
    'prefillFocusDates' => $prefillFocusDates,
    'prefillRoomTypeName' => $prefillRt->name,
    'prefillRoomCapacity' => $prefillRt->capacity,
    'prefillPrice' => $prefillDiscPrice,
    'prefillOrigPrice' => (int) $prefillRate->price_per_night,
    'prefillExtraCap' => (int) ($prefillRt->extra_capacity ?? 0),
    'prefillExtraPrice' => (int) ($prefillRt->extra_capacity_price ?? 0),
])
@else
@include('components.booking.room-types-section', [
    'accommodation' => $accommodation,
    'roomTypes' => $roomTypes,
    'mode' => $mode,
    'defaultDiscountPct' => $defaultDiscountPct,
])

@include('components.booking.quick-book-drawer', [
    'mode' => $mode,
    'defaultDiscountPct' => $defaultDiscountPct,
    'accommodationEditUrl' => $mode === 'manual'
        ? route(request()->routeIs('admin.*') ? 'admin.accommodations.edit' : 'host.accommodations.edit', $accommodation)
        : null,
    'prefillRoomTypeId' => $prefillRoomTypeId,
    'prefillRoomRateId' => $prefillRoomRateId,
    'prefillRoomId' => $prefillRoomId,
    'prefillRoomName' => $prefillRoomName,
    'prefillFocusDates' => $prefillFocusDates,
])
@endif

@if($mode === 'manual')
    <x-manual-booking.room-picker />
@endif
