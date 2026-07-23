@php
    $guestRows = $booking->guestDetails->sortBy('sort_order');
    $unassignedServices = $booking->unassignedGuestServices();
    $veteranApplied = !empty($booking->veteran_type_applied);
    $quotaEligibleCount = $booking->services->filter(fn ($s) => !$s->excluded_from_veteran_quota)->count();
    $excludedServiceCount = $booking->services->filter(fn ($s) => $s->excluded_from_veteran_quota)->count();
@endphp

@if($booking->services->isNotEmpty() || $guestRows->isNotEmpty())
    @if($veteranApplied && $booking->services->isNotEmpty())
    <div class="alert alert-info small py-2 mb-3">
        <i class="bi bi-info-circle me-1"></i>
        @if($excludedServiceCount > 0 && $quotaEligibleCount > 0)
        {{ $quotaEligibleCount }} خدمت از سهمیه/تخفیف گروه <strong>{{ $booking->veteranLabelApplied() }}</strong> مهمان اصلی استفاده می‌کند؛
        {{ $excludedServiceCount }} خدمت خارج از سهمیه (نرخ کامل یا تخفیف دستی میزبان) محاسبه شده است.
        @elseif($excludedServiceCount > 0)
        همه خدمات این رزرو خارج از سهمیه ایثارگری مهمان اصلی ثبت شده‌اند.
        @else
        تخفیف و سهمیه رایگان خدمات بر اساس گروه <strong>{{ $booking->veteranLabelApplied() }}</strong> مهمان اصلی محاسبه شده است.
        @endif
    </div>
    @endif

    @if($guestRows->isNotEmpty())
        @foreach($guestRows as $guest)
        <x-booking.guest-services-panel
            :booking="$booking"
            :guest="$guest"
            :panel="$panel ?? 'host'"
            :editable="false" />
        @endforeach
    @else
        <div class="d-flex flex-column gap-2 mb-3">
            @foreach($booking->services as $service)
            <x-booking.service-line-readonly
                :service="$service"
                :veteran-type-applied="$veteranApplied" />
            @endforeach
        </div>
    @endif

    @if($unassignedServices->isNotEmpty())
    <div class="border rounded p-2 bg-warning-subtle mb-3">
        <div class="small fw-semibold mb-2">خدمات بدون مهمان مشخص</div>
        <div class="d-flex flex-column gap-2">
            @foreach($unassignedServices as $service)
            <x-booking.service-line-readonly
                :service="$service"
                :veteran-type-applied="$veteranApplied" />
            @endforeach
        </div>
    </div>
    @endif

    <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2 small">
        <span class="text-muted">جمع خدمات (بعد از تخفیف)</span>
        <strong>{{ number_format($booking->services_subtotal) }} تومان</strong>
    </div>
@else
<p class="text-muted mb-0">خدمت اضافی ثبت نشده است.</p>
@endif
