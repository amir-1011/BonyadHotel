@php
    $panel = $panel ?? 'admin';
    $indexAllRoute = $panel === 'admin' ? 'admin.halls.index' : 'host.halls.index';
    $accIndexRoute = $panel === 'admin' ? 'admin.halls.accommodation.index' : 'host.halls.accommodation.index';
    $createRoute = $panel === 'admin' ? 'admin.halls.create' : 'host.halls.create';
    $editRoute = $panel === 'admin' ? 'admin.halls.edit' : 'host.halls.edit';
    $destroyRoute = $panel === 'admin' ? 'admin.halls.destroy' : 'host.halls.destroy';
    $showAccommodationFilter = !isset($accommodation) || !$accommodation;
@endphp

@if(session('status'))
<div class="alert alert-success py-2">{{ session('status') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif

<div class="card shadow-sm mb-3">
    <div class="ta-list-chrome">
        <span class="small text-muted">{{ $halls->count() }} سالن</span>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            @if($showAccommodationFilter && $accommodations->isNotEmpty())
            <form method="GET" action="{{ route($indexAllRoute) }}" class="d-flex gap-2">
                <select name="accommodation_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">همه اقامتگاه‌ها</option>
                    @foreach($accommodations as $acc)
                    <option value="{{ $acc->id }}" @selected((string) request('accommodation_id') === (string) $acc->id)>{{ $acc->name }}</option>
                    @endforeach
                </select>
            </form>
            @endif
            @if(isset($accommodation) && $accommodation)
                @if($panel === 'host')
                <x-host.can page="halls.create" action="write">
                <a wire:navigate href="{{ route($createRoute, $accommodation) }}" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>سالن جدید
                </a>
                </x-host.can>
                @else
                <a wire:navigate href="{{ route($createRoute, $accommodation) }}" class="btn btn-success btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>سالن جدید
                </a>
                @endif
            @elseif($accommodations->isNotEmpty())
                <div class="dropdown">
                    <button class="btn btn-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-plus-lg me-1"></i>سالن جدید
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach($accommodations as $acc)
                        <li><a class="dropdown-item" wire:navigate href="{{ route($createRoute, $acc) }}">{{ $acc->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>

@if($halls->isEmpty())
<div class="card shadow-sm text-center py-5">
    <div class="text-muted mb-3"><i class="bi bi-building fs-1"></i></div>
    <h6>هنوز سالنی تعریف نشده است</h6>
    <p class="text-muted small">انواع سالن مانند کنفرانس، همایش و سینما را برای اقامتگاه تعریف کنید.</p>
</div>
@else
<div class="row g-3">
    @foreach($halls as $hall)
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                    <div>
                        <h6 class="fw-bold mb-1">{{ $hall->name }}</h6>
                        <div class="text-muted small d-flex flex-wrap gap-3">
                            <span><i class="bi bi-tag me-1"></i>{{ $hall->typeLabel() }}</span>
                            <span><i class="bi bi-people me-1"></i>ظرفیت {{ \App\Support\PdfPersian::toPersianDigits(number_format($hall->capacity)) }} نفر</span>
                            @if($showAccommodationFilter)
                            <span><i class="bi bi-building me-1"></i>{{ $hall->accommodation?->name }}</span>
                            @endif
                        </div>
                        @if($hall->description)
                        <div class="small text-muted mt-1">{{ $hall->description }}</div>
                        @endif
                        @if($hall->amenities && count($hall->amenities))
                        <div class="mt-2 d-flex flex-wrap gap-1">
                            @foreach($hall->amenities as $amenity)
                            <span class="badge bg-light text-dark border" style="font-size:.7rem">{{ $amenity }}</span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <span class="badge bg-{{ $hall->is_active ? 'success' : 'secondary' }}">{{ $hall->is_active ? 'فعال' : 'غیرفعال' }}</span>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2 flex-wrap">
                @if($panel === 'host')
                <x-host.can page="halls.edit" action="edit">
                <a wire:navigate href="{{ route($editRoute, [$hall->accommodation, $hall]) }}" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-pencil me-1"></i>ویرایش
                </a>
                </x-host.can>
                <x-host.can page="halls.edit" action="delete">
                <form action="{{ route($destroyRoute, [$hall->accommodation, $hall]) }}" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" data-swal-confirm="این سالن حذف شود؟" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>حذف
                    </button>
                </form>
                </x-host.can>
                @else
                <a wire:navigate href="{{ route($editRoute, [$hall->accommodation, $hall]) }}" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-pencil me-1"></i>ویرایش
                </a>
                <form action="{{ route($destroyRoute, [$hall->accommodation, $hall]) }}" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" data-swal-confirm="این سالن حذف شود؟" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash me-1"></i>حذف
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
