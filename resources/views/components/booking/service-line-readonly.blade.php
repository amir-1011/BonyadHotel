{{-- Read-only display of one booking service line (matches booking-services-editor pricing UI) --}}
@props(['service', 'veteranTypeApplied' => false])

@php
    /** @var \App\Models\BookingService $service */
    $subtotal = (int) $service->unit_price * (int) $service->quantity;
    $discountAmt = (int) $service->discount_amount;
    $finalTotal = (int) $service->total;
    $excludedFromQuota = (bool) $service->excluded_from_veteran_quota;
    $discountReason = $service->discountReasonLabel();
@endphp

<div {{ $attributes->merge(['class' => 'border rounded p-2 bg-light']) }}>
    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
        <div class="flex-grow-1" style="min-width:140px;">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="small fw-semibold">{{ $service->name }}</div>
                @if($veteranTypeApplied)
                    @if($excludedFromQuota)
                    <span class="badge text-bg-warning" style="font-size:.65rem;">خارج از سهمیه ایثارگری</span>
                    @else
                    <span class="badge text-bg-success" style="font-size:.65rem;">سهمیه مهمان اصلی</span>
                    @endif
                @endif
            </div>
            <div class="text-muted" style="font-size:.7rem;">
                واحد {{ number_format($service->unit_price) }} تومان · تعداد {{ $service->quantity }}
                @if($discountAmt > 0)
                · تخفیف −{{ number_format($discountAmt) }}
                @if($discountReason !== '')
                ({{ $discountReason }})
                @endif
                @elseif($excludedFromQuota)
                · نرخ کامل
                @endif
            </div>
            @if($excludedFromQuota && $service->manual_discount_reason)
            <div class="text-muted mt-1" style="font-size:.68rem;">
                <i class="bi bi-chat-left-text me-1"></i>دلیل تخفیف: {{ $service->manual_discount_reason }}
            </div>
            @endif
        </div>
        <div class="text-end text-nowrap">
            @if($discountAmt > 0 || $subtotal !== $finalTotal)
            <div class="small text-muted" style="font-size:.68rem;">
                بدون تخفیف: {{ number_format($subtotal) }}
            </div>
            <div class="small fw-bold text-success">
                با تخفیف: {{ number_format($finalTotal) }} <span class="text-muted fw-normal">تومان</span>
            </div>
            @else
            <div class="small fw-bold">
                {{ number_format($finalTotal) }} <span class="text-muted fw-normal">تومان</span>
            </div>
            @endif
        </div>
    </div>
</div>
