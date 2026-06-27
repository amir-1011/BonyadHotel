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
            <span>− {{ number_format($lineDisc) }} ت</span>
        </div>
    </div>
@elseif($lineDisc > 0)
    <div class="d-flex justify-content-between text-danger" style="padding-right:.5rem;font-size:{{ $compact ? '.72rem' : '.78rem' }}">
        <span>
            <i class="bi bi-tag-fill me-1" style="font-size:.75rem"></i>
            تخفیف {{ (int) ($line['discount_percentage'] ?? 0) }}٪
            @if(($line['free_units'] ?? 0) > 0)
                · {{ $line['free_units'] }} جلسه رایگان
            @endif
        </span>
        <span class="fw-semibold">− {{ number_format($lineDisc) }} ت</span>
    </div>
@elseif(($line['line_subtotal'] ?? 0) === 0)
    <div class="d-flex justify-content-between text-success" style="padding-right:.5rem;font-size:{{ $compact ? '.72rem' : '.78rem' }}">
        <span><i class="bi bi-gift-fill me-1" style="font-size:.75rem"></i>رایگان</span>
        <span>—</span>
    </div>
@endif
