@props([
    'countFiltered',
    'totalFiltered' => null,
])

<div class="d-flex flex-wrap gap-3 align-items-center mb-3">
    <div class="card border-0 shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-2">
        <i class="bi bi-receipt fs-5 text-primary"></i>
        <div>
            <div class="text-muted" style="font-size:.72rem">تعداد {{ $totalFiltered !== null ? 'رزروهای فیلتر شده' : 'کاربران فیلتر شده' }}</div>
            <div class="fw-bold">{{ number_format($countFiltered) }} {{ $totalFiltered !== null ? 'رزرو' : 'کاربر' }}</div>
        </div>
    </div>
    @if($totalFiltered !== null)
    <div class="card border-0 shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-2">
        <i class="bi bi-cash-coin fs-5 text-success"></i>
        <div>
            <div class="text-muted" style="font-size:.72rem">جمع کل مبالغ (فیلتر شده)</div>
            <div class="fw-bold text-success">{{ number_format($totalFiltered) }} تومان</div>
        </div>
    </div>
    @endif
</div>
