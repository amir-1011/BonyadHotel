@props([
    'countFiltered',
    'totalFiltered' => null,
])

<div {{ $attributes->class('ta-filter-stats') }}>
    <div class="ta-filter-stat">
        <i class="bi {{ $totalFiltered !== null ? 'bi-receipt' : 'bi-people' }}"></i>
        <span class="ta-filter-stat-value">{{ \App\Support\PdfPersian::toPersianDigits(number_format($countFiltered)) }}</span>
        <span class="ta-filter-stat-label">{{ $totalFiltered !== null ? 'رزرو فیلترشده' : 'کاربر فیلترشده' }}</span>
    </div>
    @if($totalFiltered !== null)
    <div class="ta-filter-stat">
        <i class="bi bi-cash-coin"></i>
        <span class="ta-filter-stat-value ta-filter-stat-value--ok">{{ \App\Support\PdfPersian::toPersianDigits(number_format($totalFiltered)) }}</span>
        <span class="ta-filter-stat-label">ریال</span>
    </div>
    @endif
</div>
