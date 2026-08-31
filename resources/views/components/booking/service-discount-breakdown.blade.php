{{-- جزئیات تخفیف یک ردیف خدمت (پله‌ای یا ساده) --}}
@props(['line', 'compact' => false])

@php
    $breakdown = $line['discount_breakdown'] ?? [];
    $lineDisc = (int) ($line['discount_amount'] ?? 0);
@endphp

@if($lineDisc > 0 && !empty($breakdown))
    <div class="{{ $compact ? '' : 'mt-1' }}" style="padding-right:.5rem">
        @foreach($breakdown as $item)
        <div class="d-flex justify-content-between {{ $compact ? 'small' : '' }} text-danger" style="font-size:{{ $compact ? '.72rem' : '.78rem' }}">
            <span>
                <i class="bi bi-tag-fill me-1" style="font-size:.7rem"></i>
                {{ \App\Services\ServiceDiscountTierEngine::describeBreakdownItem($item) }}
            </span>
        </div>
        @endforeach
        <div class="d-flex justify-content-between fw-semibold text-danger {{ $compact ? 'small' : '' }}" style="font-size:{{ $compact ? '.72rem' : '.8rem' }}">
            <span>جمع تخفیف این خدمت</span>
            <span>− {{ \App\Support\PdfPersian::toPersianDigits(number_format($lineDisc)) }} ریال</span>
        </div>
    </div>
@elseif($lineDisc > 0)
    @php
        $discountReason = \App\Models\BookingService::describeDiscountFromAttributes([
            'discount_amount'             => $lineDisc,
            'excluded_from_veteran_quota' => $line['excluded_from_veteran_quota'] ?? false,
            'manual_discount_percentage'  => $line['manual_discount_percentage'] ?? null,
            'quantity'                    => (int) ($line['quantity'] ?? 0),
            'unit_price'                  => (int) ($line['unit_price'] ?? 0),
            'free_units'                  => (int) ($line['free_units'] ?? 0),
            'discount_percentage'         => (int) ($line['discount_percentage'] ?? 0),
        ]);
    @endphp
    <div class="d-flex justify-content-between text-danger" style="padding-right:.5rem;font-size:{{ $compact ? '.72rem' : '.78rem' }}">
        <span>
            <i class="bi bi-tag-fill me-1" style="font-size:.75rem"></i>
            @if($discountReason !== '')
            {{ $discountReason }}
            @else
            تخفیف
            @endif
        </span>
        <span class="fw-semibold">− {{ \App\Support\PdfPersian::toPersianDigits(number_format($lineDisc)) }} ریال</span>
    </div>
@elseif(($line['line_subtotal'] ?? 0) === 0)
    <div class="d-flex justify-content-between text-success" style="padding-right:.5rem;font-size:{{ $compact ? '.72rem' : '.78rem' }}">
        <span><i class="bi bi-gift-fill me-1" style="font-size:.75rem"></i>رایگان</span>
        <span>—</span>
    </div>
@endif
