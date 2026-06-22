@props([
    'accommodation',
    'roomType',
    'routePrefix',
])

@php
    $storeUrl = route($routePrefix . '.rates.store', [$accommodation, $roomType]);
    $showAddForm = $errors->hasBag('default') || old('price_per_night');
@endphp

<div class="card shadow-sm rt-rates-card">
    <div class="card-header fw-bold d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-tags"></i>
            <span>تعرفه‌های این اتاق</span>
            @if($roomType->rates->isNotEmpty())
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $roomType->rates->count() }} تعرفه</span>
            @endif
        </div>
        <button class="btn btn-sm btn-success" type="button"
                data-bs-toggle="collapse" data-bs-target="#addRateForm"
                aria-expanded="{{ $showAddForm ? 'true' : 'false' }}">
            <i class="bi bi-plus-lg me-1"></i>تعرفه جدید
        </button>
    </div>

    <div class="collapse {{ $showAddForm ? 'show' : '' }}" id="addRateForm">
        <div class="rt-rates-add-panel">
            <div class="rt-rates-add-panel__title">
                <i class="bi bi-plus-circle text-success"></i>
                <span>افزودن تعرفه جدید</span>
            </div>
            <form action="{{ $storeUrl }}" method="POST">
                @csrf
                <x-room-type.rate-form :rate="null" />
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-check-lg me-1"></i>ذخیره تعرفه
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-toggle="collapse" data-bs-target="#addRateForm">انصراف</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card-body {{ $roomType->rates->isEmpty() ? 'py-5' : 'p-0' }}">
        @if($roomType->rates->isEmpty())
        <div class="text-center text-muted rt-rates-empty">
            <div class="rt-rates-empty__icon"><i class="bi bi-tags"></i></div>
            <p class="fw-semibold mb-1">هنوز تعرفه‌ای تعریف نشده</p>
            <p class="small mb-3">برای نمایش این اتاق در سایت حداقل یک تعرفه اضافه کنید.</p>
            <button class="btn btn-sm btn-success" type="button"
                    data-bs-toggle="collapse" data-bs-target="#addRateForm">
                <i class="bi bi-plus-lg me-1"></i>اولین تعرفه را اضافه کنید
            </button>
        </div>
        @else
        <div class="rt-rates-list">
            @foreach($roomType->rates as $rate)
            @php
                $updateUrl = route($routePrefix . '.rates.update', [$accommodation, $roomType, $rate]);
                $destroyUrl = route($routePrefix . '.rates.destroy', [$accommodation, $roomType, $rate]);
            @endphp
            <div class="rt-rate-item {{ !$rate->is_active ? 'rt-rate-item--inactive' : '' }}">
                <div class="rt-rate-item__main">
                    <div class="rt-rate-item__info">
                        <div class="rt-rate-item__name">{{ $rate->name }}</div>
                        <div class="rt-rate-item__price">
                            {{ number_format($rate->price_per_night) }}
                            <small>تومان / شب</small>
                        </div>
                        <div class="rt-rate-item__badges">
                            @if($rate->breakfast_included)
                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                <i class="bi bi-cup-hot me-1"></i>صبحانه رایگان
                            </span>
                            @else
                            <span class="badge rounded-pill bg-light text-muted border">بدون صبحانه</span>
                            @endif
                            <span class="badge rounded-pill bg-{{ $rate->cancellation_policy === 'free' ? 'success' : 'danger' }}-subtle text-{{ $rate->cancellation_policy === 'free' ? 'success' : 'danger' }}-emphasis border border-{{ $rate->cancellation_policy === 'free' ? 'success' : 'danger' }}-subtle">
                                {{ $rate->cancellationLabel() }}
                            </span>
                            <span class="badge rounded-pill bg-{{ $rate->is_active ? 'success' : 'secondary' }}-subtle text-{{ $rate->is_active ? 'success' : 'secondary' }}-emphasis border">
                                {{ $rate->is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </div>
                    </div>
                    <div class="rt-rate-item__actions">
                        <button class="btn btn-sm btn-outline-warning" title="ویرایش" type="button"
                                data-bs-toggle="collapse" data-bs-target="#editRate{{ $rate->id }}">
                            <i class="bi bi-pencil me-1"></i>ویرایش
                        </button>
                        <form action="{{ $destroyUrl }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="submit" data-swal-confirm="این تعرفه حذف شود؟"
                                    class="btn btn-sm btn-outline-danger" title="حذف">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="collapse" id="editRate{{ $rate->id }}">
                    <div class="rt-rate-item__edit">
                        <div class="rt-rates-add-panel__title mb-3">
                            <i class="bi bi-pencil text-warning"></i>
                            <span>ویرایش تعرفه: {{ $rate->name }}</span>
                        </div>
                        <form action="{{ $updateUrl }}" method="POST">
                            @csrf @method('PUT')
                            <x-room-type.rate-form :rate="$rate" />
                            <div class="mt-3 d-flex gap-2">
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="bi bi-check-lg me-1"></i>ذخیره تغییرات
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="collapse" data-bs-target="#editRate{{ $rate->id }}">انصراف</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@once
@push('styles')
<style>
.rt-rates-card .card-header { background: var(--bs-body-bg); }
.rt-rates-add-panel {
    padding: 1.25rem 1.5rem;
    background: rgba(var(--bs-success-rgb), .05);
    border-bottom: 1px solid var(--bs-border-color);
}
.rt-rates-add-panel__title {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-weight: 600;
    margin-bottom: 1rem;
}
.rt-rates-empty__icon {
    width: 3.5rem;
    height: 3.5rem;
    margin: 0 auto .75rem;
    border-radius: 50%;
    background: var(--bs-light);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--bs-secondary);
}
.rt-rates-list { display: flex; flex-direction: column; }
.rt-rate-item {
    border-bottom: 1px solid var(--bs-border-color);
    transition: background .15s;
}
.rt-rate-item:last-child { border-bottom: none; }
.rt-rate-item:hover { background: rgba(var(--bs-primary-rgb), .02); }
.rt-rate-item--inactive { opacity: .75; }
.rt-rate-item__main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    flex-wrap: wrap;
}
.rt-rate-item__name {
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: .25rem;
}
.rt-rate-item__price {
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--bs-primary);
    margin-bottom: .5rem;
}
.rt-rate-item__price small {
    font-size: .75rem;
    font-weight: 500;
    color: var(--bs-secondary);
}
.rt-rate-item__badges {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}
.rt-rate-item__actions {
    display: flex;
    align-items: center;
    gap: .35rem;
    flex-shrink: 0;
}
.rt-rate-item__edit {
    padding: 1rem 1.25rem 1.25rem;
    background: rgba(var(--bs-warning-rgb), .05);
    border-top: 1px dashed var(--bs-border-color);
}

/* Rate form options */
.rt-rate-options {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
}
.rt-rate-option {
    flex: 1;
    min-width: 140px;
    margin: 0;
    cursor: pointer;
}
.rt-rate-option--wide { display: block; }
.rt-rate-option-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.rt-rate-option-body {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .65rem .85rem;
    border: 1.5px solid var(--bs-border-color);
    border-radius: .5rem;
    background: var(--bs-body-bg);
    font-size: .9rem;
    transition: border-color .15s, background .15s;
    height: 100%;
}
.rt-rate-option:hover .rt-rate-option-body {
    border-color: rgba(var(--bs-primary-rgb), .45);
}
.rt-rate-option:has(.rt-rate-option-input:checked) .rt-rate-option-body {
    border-color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), .08);
    font-weight: 600;
}
.rt-rate-breakfast-tile {
    display: block;
    margin: 0;
    cursor: pointer;
}
.rt-rate-breakfast-tile__body {
    display: flex;
    align-items: center;
    gap: .85rem;
    padding: .85rem 1rem;
    border: 1.5px solid var(--bs-border-color);
    border-radius: .65rem;
    background: var(--bs-body-bg);
    transition: border-color .15s, background .15s;
}
.rt-rate-breakfast-tile__icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: .5rem;
    background: rgba(var(--bs-warning-rgb), .12);
    color: var(--bs-warning);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    flex-shrink: 0;
}
.rt-rate-breakfast-tile__title {
    display: block;
    font-weight: 600;
    font-size: .95rem;
}
.rt-rate-breakfast-tile__hint {
    display: block;
    font-size: .78rem;
    color: var(--bs-secondary);
    margin-top: .1rem;
}
.rt-rate-breakfast-tile__check {
    margin-inline-start: auto;
    color: var(--bs-warning);
    font-size: 1.1rem;
    opacity: 0;
    transition: opacity .15s;
}
.rt-rate-breakfast-tile--on .rt-rate-breakfast-tile__body {
    border-color: var(--bs-warning);
    background: rgba(var(--bs-warning-rgb), .08);
}
.rt-rate-breakfast-tile--on .rt-rate-breakfast-tile__check { opacity: 1; }
</style>
@endpush
@endonce
