{{--
    Final payable line for a booking service in financial breakdown.
    @param \App\Models\BookingService $service
--}}
@props(['service'])

@php
    /** @var \App\Models\BookingService $service */
    $calculatedTotal = $service->calculatedTotal();
    $manualAdjustment = $service->manualPriceAdjustmentAmount();
    $payableTotal = $service->payableTotal();
@endphp

@if($manualAdjustment !== 0)
    <div class="d-flex justify-content-between small text-muted mb-1">
        <span>محاسبه خودکار</span>
        <span dir="ltr">{{ \App\Support\PdfPersian::toPersianDigits(number_format($calculatedTotal)) }} ریال</span>
    </div>
    <x-booking.manual-total-adjustment-note :adjustment="$manualAdjustment" class="small mb-1" />
    <div class="d-flex justify-content-between small fw-semibold text-primary pt-1 border-top border-primary border-opacity-10">
        <span>مبلغ ثبت‌شده</span>
        <span dir="ltr">{{ \App\Support\PdfPersian::toPersianDigits(number_format($payableTotal)) }} ریال</span>
    </div>
@else
    <div class="d-flex justify-content-between small text-success fw-semibold">
        <span>با تخفیف</span>
        <span dir="ltr">{{ \App\Support\PdfPersian::toPersianDigits(number_format($calculatedTotal)) }} ریال</span>
    </div>
@endif
