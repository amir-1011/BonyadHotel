{{-- جزئیات تخفیف اقامت (تک‌گروهی، چندگروهی یا پلکانی) --}}
@props(['breakdown' => [], 'total' => 0, 'compact' => false, 'tiered' => false])

@if($total > 0 && !empty($breakdown))
    <div class="{{ $compact ? '' : 'mt-1' }}" style="padding-right:.5rem">
        @if($tiered && count($breakdown) > 1)
        <div class="text-muted {{ $compact ? 'small' : '' }} mb-1" style="font-size:{{ $compact ? '.68rem' : '.72rem' }}">
            جزئیات پله‌های تخفیف اقامت
        </div>
        @endif
        @foreach($breakdown as $item)
        <div class="text-danger {{ $compact ? 'small' : '' }}" style="font-size:{{ $compact ? '.72rem' : '.78rem' }}">
            <i class="bi bi-layers-fill me-1" style="font-size:.7rem"></i>
            {{ \App\Services\VeteranPolicyService::describeAccommodationBreakdownItem($item) }}
        </div>
        @endforeach
        <div class="d-flex justify-content-between fw-semibold text-danger {{ $compact ? 'small' : '' }}" style="font-size:{{ $compact ? '.72rem' : '.8rem' }}">
            <span>جمع تخفیف اقامت</span>
            <span>− {{ \App\Support\PdfPersian::toPersianDigits(number_format($total)) }} ریال</span>
        </div>
    </div>
@elseif($total > 0)
    <div class="d-flex justify-content-between text-danger" style="padding-right:.5rem;font-size:{{ $compact ? '.72rem' : '.78rem' }}">
        <span><i class="bi bi-tag-fill me-1" style="font-size:.75rem"></i>تخفیف اقامت</span>
        <span class="fw-semibold">− {{ \App\Support\PdfPersian::toPersianDigits(number_format($total)) }} ریال</span>
    </div>
@endif
