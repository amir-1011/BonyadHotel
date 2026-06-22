@props(['accommodationId', 'defaultDiscountPct' => 0, 'includeCalMixin' => true, 'childAllocateBed' => true, 'childDiscountPct' => 50])
@once
@push('styles')
@include('components.booking.styles')
@endpush
@push('scripts')
@include('components.booking.scripts-core', [
    'accommodationId' => $accommodationId,
    'defaultDiscountPct' => $defaultDiscountPct,
    'includeCalMixin' => $includeCalMixin,
    'childAllocateBed' => $childAllocateBed,
    'childDiscountPct' => $childDiscountPct,
])
@endpush
@endonce
