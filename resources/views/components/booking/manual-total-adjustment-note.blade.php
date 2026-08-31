{{--
    Shows manual total-price adjustment relative to auto-calculated total.
    @param int $adjustment  signed delta (display − natural)
    @param bool $badge  compact badge vs full line
    @param bool $showNatural  include auto-calculated total in full line mode
    @param int|null $naturalTotal
--}}
@props([
    'adjustment' => 0,
    'badge' => false,
    'showNatural' => false,
    'naturalTotal' => null,
])

@php
    $adjustment = (int) $adjustment;
@endphp

@if($adjustment !== 0)
    @php
        $signedAmount = ($adjustment > 0 ? '+' : '−') . \App\Support\PdfPersian::toPersianDigits(number_format(abs($adjustment)));
        $toneClass = $adjustment > 0 ? 'text-warning-emphasis' : 'text-danger';
    @endphp

    @if($badge)
        <span {{ $attributes->merge(['class' => 'badge bg-warning-subtle text-dark border border-warning-subtle']) }} title="اختلاف مبلغ قابل پرداخت با محاسبه خودکار سیستم">
            تعدیل مبلغ {{ $signedAmount }} ریال
        </span>
    @else
        <div {{ $attributes->merge(['class' => 'd-flex justify-content-between py-1 ' . $toneClass]) }}>
            <span class="text-muted">
                تعدیل مبلغ رزرو
                @if($showNatural && $naturalTotal !== null)
                    <span class="d-block small mt-1" style="font-size:.75rem">
                        (محاسبه خودکار: {{ \App\Support\PdfPersian::toPersianDigits(number_format((int) $naturalTotal)) }} ریال)
                    </span>
                @endif
            </span>
            <span class="fw-semibold">{{ $signedAmount }} ریال</span>
        </div>
    @endif
@endif
