{{--
    Per-guest services block for booking show / modals.
    @param \App\Models\Booking $booking
    @param \App\Models\BookingGuestDetail|object $guest
    @param string $panel  admin|host
    @param bool $editable
--}}
@props(['booking', 'guest', 'panel' => 'host', 'editable' => false])

@php
    $sortOrder = (int) ($guest->sort_order ?? 0);
    $guestServices = $booking->servicesForGuest($sortOrder);
    $roomLabel = $guest instanceof \App\Models\BookingGuestDetail
        ? $booking->guestPhysicalRoomLabel($guest)
        : null;
    $veteranApplied = !empty($booking->veteran_type_applied);
    $excludedStay = !empty($guest->excluded_from_veteran_discount);
@endphp

<div class="border rounded mb-3 overflow-hidden">
    <div class="bg-light px-3 py-2 border-bottom">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="badge text-bg-secondary">نفر {{ $sortOrder + 1 }}</span>
            <strong class="small">{{ $guest->full_name }}</strong>
            @if($sortOrder === 0)
            <span class="badge text-bg-primary" style="font-size:.65rem;">مهمان اصلی</span>
            @endif
            @if($guest->relation ?? null)
            <span class="badge bg-white text-muted border">{{ \App\Models\BookingGuestDetail::formatRelationLabel($guest->relation ?? null) }}</span>
            @endif
            @if($roomLabel)
            <span class="badge text-bg-dark" style="font-size:.65rem;"><i class="bi bi-door-closed me-1"></i>اتاق {{ $roomLabel }}</span>
            @endif
        </div>
        @if($guest->identityNumber() || $guest->mobile || $guest->residenceLocationLabel())
        <div class="text-muted mt-1" style="font-size:.72rem;">
            @if($guest->identityNumber())
            <span dir="ltr">{{ $guest->identityFieldLabel() }}: {{ $guest->identityNumber() }}</span>
            @endif
            @if($guest->residenceLocationLabel())
            <span class="{{ $guest->identityNumber() ? 'ms-2' : '' }}">محل اقامت: {{ $guest->residenceLocationLabel() }}</span>
            @endif
            @if($guest->mobile)<span class="ms-2" dir="ltr">موبایل: {{ $guest->mobile }}</span>@endif
        </div>
        @endif
        <div class="mt-2 d-flex flex-wrap gap-1">
            @if($veteranApplied)
            <span class="badge {{ $excludedStay ? 'text-bg-warning' : 'text-bg-success' }}" style="font-size:.65rem;">
                اقامت: {{ $excludedStay ? 'نرخ عادی (بدون تخفیف ایثارگری)' : 'شامل تخفیف ایثارگری' }}
            </span>
            @endif
            @if((int) ($guest->manual_discount_percentage ?? 0) > 0)
            <span class="badge text-bg-info" style="font-size:.65rem;">
                تخفیف دستی اقامت {{ $guest->manual_discount_percentage }}٪
                @if($guest->manual_discount_reason ?? null)
                · {{ $guest->manual_discount_reason }}
                @endif
            </span>
            @endif
        </div>
    </div>
    <div class="p-2">
        @if($editable && $booking->canEditServices())
        <livewire:booking-services-editor
            :booking-id="$booking->id"
            :panel="$panel"
            :guest-sort-order="$sortOrder"
            :key="'booking-show-services-'.$booking->id.'-guest-'.$sortOrder" />
        @elseif($guestServices->isNotEmpty())
        <div class="d-flex flex-column gap-2">
            @foreach($guestServices as $service)
            <x-booking.service-line-readonly
                :service="$service"
                :veteran-type-applied="$veteranApplied"
                wire:key="guest-svc-ro-{{ $service->id }}" />
            @endforeach
        </div>
        @else
        <div class="alert alert-light border small py-2 mb-0">
            <i class="bi bi-info-circle me-1"></i>خدمتی برای این مهمان ثبت نشده است.
        </div>
        @endif
    </div>
</div>
