@extends('layouts.admin')

@section('pageTitle')
اتاق‌های {{ $accommodation->name }}
@endsection

@section('content')
<div>

@if($roomTypes->isEmpty())
<div class="card shadow-sm text-center py-5">
    <div class="text-muted mb-3"><i class="bi bi-door-open fs-1"></i></div>
    <h6>هنوز اتاقی تعریف نشده است</h6>
    <p class="text-muted small">با تعریف انواع اتاق و تعرفه‌ها (قیمت به ازای هر تخت)، مهمانان می‌توانند اتاق موردنظر را انتخاب کنند.</p>
    <a wire:navigate href="{{ route('admin.room-types.create', $accommodation) }}" class="btn btn-success mt-2">تعریف اولین اتاق</a>
</div>
@else
<div class="card shadow-sm mb-3">
    <div class="ta-list-chrome">
        <span class="small text-muted">{{ $roomTypes->count() }} نوع اتاق</span>
        <a wire:navigate href="{{ route('admin.room-types.create', $accommodation) }}" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg me-1"></i>اتاق جدید
        </a>
    </div>
</div>
<div class="row g-3">
    @foreach($roomTypes as $rt)
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row align-items-start">
                    <div class="col-auto">
                        @php $cover = $rt->coverImage(); @endphp
                        @if($cover)
                            <img src="{{ asset('storage/'.$cover) }}" alt="{{ $rt->name }}" class="rounded" style="width:100px;height:80px;object-fit:cover;">
                        @else
                            <div class="rounded bg-light d-flex align-items-center justify-content-center text-muted" style="width:100px;height:80px;">
                                <i class="bi bi-image fs-2"></i>
                            </div>
                        @endif
                    </div>
                    <div class="col">
                        <div class="d-flex align-items-start justify-content-between">
                            <div>
                                <h6 class="fw-bold mb-1">{{ $rt->name }}</h6>
                                <div class="text-muted small d-flex flex-wrap gap-3">
                                    @if($rt->bed_type)<span><i class="bi bi-moon-stars me-1"></i>{{ $rt->bed_type }}</span>@endif
                                    <span><i class="bi bi-people me-1"></i>ظرفیت {{ $rt->capacity }} نفر</span>
                                    @if($rt->size_sqm)<span><i class="bi bi-aspect-ratio me-1"></i>{{ $rt->size_sqm }} متر مربع</span>@endif
                                    <span><i class="bi bi-door-closed me-1"></i>{{ $rt->room_count }} اتاق</span>
                                    @if($rt->smoking)<span class="text-warning"><i class="bi bi-slash-circle me-1"></i>سیگاری</span>@endif
                                </div>
                                @if($rt->amenities && count($rt->amenities))
                                <div class="mt-1 d-flex flex-wrap gap-1">
                                    @foreach($rt->amenities as $a)
                                    <span class="badge bg-light text-dark border" style="font-size:.7rem">{{ $a }}</span>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                            <span class="badge bg-{{ $rt->is_active ? 'success' : 'secondary' }} ms-2">{{ $rt->is_active ? 'فعال' : 'غیرفعال' }}</span>
                        </div>

                        @if($rt->rates->isNotEmpty())
                        <div class="mt-2">
                            <div class="text-muted small mb-1"><i class="bi bi-tags me-1"></i>تعرفه‌ها:</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($rt->rates as $rate)
                                <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle" style="font-size:.75rem">
                                    {{ $rate->name }} — {{ \App\Support\PdfPersian::toPersianDigits(number_format($rate->price_per_night)) }} ریال/شب/تخت
                                    @if($rate->breakfast_included) <i class="bi bi-cup-hot ms-1 text-warning"></i> @endif
                                </span>
                                @endforeach
                            </div>
                        </div>
                        @else
                        <div class="mt-2 text-warning small"><i class="bi bi-exclamation-circle me-1"></i>هنوز تعرفه‌ای تعریف نشده — برای نمایش در سایت باید حداقل یک تعرفه اضافه کنید.</div>
                        @endif

                        @if($rt->rooms->isNotEmpty())
                        <div class="mt-3">
                            <div class="text-muted small mb-2"><i class="bi bi-grid-3x3-gap me-1"></i>اتاق‌های فیزیکی:</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($rt->rooms as $room)
                                <div class="border rounded px-2 py-1 bg-light" style="font-size:.72rem;min-width:100px;">
                                    <div class="fw-semibold">{{ $room->name }}</div>
                                    @if($room->description)
                                    <div class="text-muted text-truncate" style="max-width:140px;" title="{{ $room->description }}">{{ Str::limit($room->description, 40) }}</div>
                                    @endif
                                    @if($room->displayAmenities())
                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                        @foreach(array_slice($room->displayAmenities(), 0, 3) as $a)
                                        <span class="badge bg-white text-dark border">{{ $a }}</span>
                                        @endforeach
                                        @if(count($room->displayAmenities()) > 3)
                                        <span class="badge bg-secondary-subtle text-secondary">+{{ count($room->displayAmenities()) - 3 }}</span>
                                        @endif
                                    </div>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2 flex-wrap">
                <a wire:navigate href="{{ route('admin.room-types.edit', [$accommodation, $rt]) }}" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-pencil me-1"></i>ویرایش و مدیریت تعرفه‌ها
                </a>
                <a wire:navigate href="{{ route('admin.room-types.blocked-dates', [$accommodation, $rt]) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-calendar-x me-1"></i>مسدودسازی تاریخ
                </a>
                <a wire:navigate href="{{ route('admin.room-types.daily-availability', [$accommodation, $rt]) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-sliders me-1"></i>سیاست‌های قیمتی
                </a>
                <form action="{{ route('admin.room-types.destroy', [$accommodation, $rt]) }}" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" data-swal-confirm="این اتاق و تمام تعرفه‌هایش حذف شود؟" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>حذف</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

</div>
@endsection
