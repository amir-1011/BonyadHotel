@props([
    'accommodation',
    'roomTypes' => null,
    'mode' => 'manual',
    'defaultDiscountPct' => 0,
])
@php
    $roomTypes = $roomTypes ?? $accommodation->roomTypes;
@endphp

@include('components.booking.assets', [
    'accommodationId' => $accommodation->id,
    'defaultDiscountPct' => $defaultDiscountPct,
    'childAllocateBed' => $accommodation->childrenUnder6AllocateBed(),
    'childDiscountPct' => $accommodation->childrenUnder6DiscountPercentage(),
])

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
])
