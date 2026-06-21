@props(['accommodationId', 'defaultDiscountPct' => 0, 'includeCalMixin' => true])
@once
@push('styles')
@include('components.booking.styles')
@endpush
@push('scripts')
@include('components.booking.scripts-core', [
    'accommodationId' => $accommodationId,
    'defaultDiscountPct' => $defaultDiscountPct,
    'includeCalMixin' => $includeCalMixin,
])
@endpush
@endonce
