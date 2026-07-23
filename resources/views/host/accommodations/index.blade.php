<div>

@php($hostUser = Auth::user())

<div class="d-flex align-items-center justify-content-end mb-3">
    @if($hostUser->hostCan('accommodations.create', 'write'))
    <a wire:navigate href="{{ route('host.accommodations.create') }}" class="btn btn-sm btn-success">
        <i class="bi bi-plus-lg me-1"></i>اقامتگاه جدید
    </a>
    @endif
</div>

<x-tutorial-videos :videos="[
    ['label' => 'ثبت اقامتگاه', 'file' => 'اقامتگاه.mp4'],
    ['label' => 'رزرو دستی', 'file' => 'رزرو.mp4'],
]" />

@if($accommodations->isEmpty())
    <div class="card shadow-sm text-center py-5">
        <div class="text-muted mb-3"><i class="bi bi-building fs-1"></i></div>
        <h6>هنوز اقامتگاهی ثبت نکرده‌اید</h6>
        @if($hostUser->hostCan('accommodations.create', 'write'))
        <a wire:navigate href="{{ route('host.accommodations.create') }}" class="btn btn-success mt-2">ثبت اولین اقامتگاه</a>
        @endif
    </div>
@else
<div class="row g-3">
    @foreach($accommodations as $acc)
    <div class="col-12 col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div>
                        <h6 class="fw-bold mb-1">{{ $acc->name }}</h6>
                        <div class="text-muted small">{{ $acc->city->name ?? '' }} — {{ $acc->typeLabel() }}</div>
                    </div>
                    <span class="badge bg-{{ $acc->is_active ? 'success' : 'secondary' }}">{{ $acc->is_active ? 'فعال' : 'غیرفعال' }}</span>
                </div>
                <div class="row text-center g-2 mt-1">
                    <div class="col-6">
                        <div class="fw-bold">{{ $acc->capacity }}</div>
                        <div class="text-muted" style="font-size:.7rem">ظرفیت</div>
                    </div>
                    <div class="col-6">
                        <a wire:navigate href="{{ route('host.bookings.index', ['accommodation_id'=> $acc->id]) }}" class="text-decoration-none">
                            <div class="fw-bold text-success">{{ $acc->bookings_count }}</div>
                            <div class="text-muted" style="font-size:.7rem">رزرو</div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2 flex-wrap">
                <a href="{{ route('accommodations.show', $acc) }}" class="btn btn-sm btn-outline-secondary flex-fill" target="_blank">
                    <i class="bi bi-eye me-1"></i>نمایش
                </a>
                @if($hostUser->hostCan('accommodations.manual-booking', 'write'))
                <a wire:navigate href="{{ route('host.accommodations.manual-booking', $acc) }}" class="btn btn-sm btn-success flex-fill">
                    <i class="bi bi-plus-circle me-1"></i>رزرو دستی
                </a>
                @endif
                @if($hostUser->hostCan('room-types.list', 'read'))
                <a wire:navigate href="{{ route('host.room-types.index', $acc) }}" class="btn btn-sm btn-outline-info flex-fill">
                    <i class="bi bi-door-open me-1"></i>اتاق‌ها
                </a>
                @endif
                @if($hostUser->hostCan('bookings.list', 'read'))
                <a wire:navigate href="{{ route('host.bookings.index', ['accommodation_id'=> $acc->id]) }}" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="bi bi-calendar-check me-1"></i>رزروها
                </a>
                @endif
                @if($hostUser->hostCan('accommodations.report', 'read'))
                <a wire:navigate href="{{ route('host.accommodations.report', $acc) }}" class="btn btn-sm btn-primary flex-fill">
                    <i class="bi bi-graph-up-arrow me-1"></i>گزارش
                </a>
                @endif
                @if($hostUser->hostCanAny('accommodations.veteran-policy', ['read', 'edit']))
                <a wire:navigate href="{{ route('host.accommodations.veteran-policy', $acc) }}" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="bi bi-shield-check me-1"></i>تعاریف اولیه
                </a>
                @endif
                @if($hostUser->hostCanAny('accommodations.cancellation-policy', ['read', 'edit']))
                <a wire:navigate href="{{ route('host.accommodations.cancellation-policy', $acc) }}" class="btn btn-sm btn-outline-primary flex-fill">
                    <i class="bi bi-x-circle me-1"></i>سیاست کنسلی
                </a>
                @endif
                @if($hostUser->hostCanAny('accommodations.edit', ['read', 'edit']))
                <a wire:navigate href="{{ route('host.accommodations.edit', $acc) }}" class="btn btn-sm btn-outline-warning flex-fill">
                    <i class="bi bi-pencil me-1"></i>ویرایش
                </a>
                @endif
                @if($hostUser->hostCan('accommodations.list', 'delete'))
                <button wire:click="destroy({{ $acc->id }})" data-swal-confirm="حذف شود؟" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

</div>