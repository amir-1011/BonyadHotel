{{-- جزئیات تخفیف اقامت (تک‌گروهی یا چندگروهی) --}}
@props(['breakdown' => [], 'total' => 0, 'compact' => false])

@if($total > 0 && !empty($breakdown))
    <div class="{{ $compact ? '' : 'mt-1' }}" style="padding-right:.5rem">
        @foreach($breakdown as $item)
        <div class="text-danger {{ $compact ? 'small' : '' }}" style="font-size:{{ $compact ? '.72rem' : '.78rem' }}">
            <i class="bi bi-tag-fill me-1" style="font-size:.7rem"></i>
            {{ \App\Services\VeteranPolicyService::describeAccommodationBreakdownItem($item) }}
        </div>
        @endforeach
        <div class="d-flex justify-content-between fw-semibold text-danger {{ $compact ? 'small' : '' }}" style="font-size:{{ $compact ? '.72rem' : '.8rem' }}">
            <span>جمع تخفیف اقامت</span>
            <span>− {{ number_format($total) }} ت</span>
        </div>
    </div>
@elseif($total > 0)
    <div class="d-flex justify-content-between text-danger" style="padding-right:.5rem;font-size:{{ $compact ? '.72rem' : '.78rem' }}">
        <span><i class="bi bi-tag-fill me-1" style="font-size:.75rem"></i>تخفیف اقامت</span>
        <span class="fw-semibold">− {{ number_format($total) }} ت</span>
    </div>
@endif
