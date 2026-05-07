@extends('layouts.app')

@section('title', 'جستجوی اقامتگاه')

@section('content')

{{-- Search Filter Bar --}}
<div class="card p-3 mb-4 shadow-sm">
    <form action="{{ route('accommodations.index') }}" method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold">استان</label>
            <select name="province_id" id="provinceSelect" class="form-select form-select-sm select2-basic">
                <option value="">همه استان‌ها</option>
                @foreach($provinces as $province)
                    <option value="{{ $province->id }}" {{ request('province_id') == $province->id ? 'selected' : '' }}>
                        {{ $province->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold">شهر</label>
            <select name="city_id" id="citySelect" class="form-select form-select-sm select2-basic" {{ $cities->isEmpty() ? 'disabled' : '' }}>
                <option value="">همه شهرها</option>
                @foreach($cities as $city)
                    <option value="{{ $city->id }}" {{ request('city_id') == $city->id ? 'selected' : '' }}>
                        {{ $city->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold">بازه تاریخ اقامت</label>
            <div class="range-picker-trigger border rounded-3 px-2 py-1 bg-white d-flex align-items-center justify-content-between"
                 data-bs-toggle="collapse" data-bs-target="#indexDateCal">
                <div id="indexDateDisplay" style="font-size:.85rem"><span class="text-muted">انتخاب تاریخ</span></div>
                <i class="bi bi-calendar3 text-primary"></i>
            </div>
            <div class="range-picker-phase text-info">کلیک اول: ورود — کلیک دوم: خروج</div>
            <div class="collapse mt-1" id="indexDateCal">
                <div class="range-picker-cal"><div id="indexCalEl"></div></div>
            </div>
            <input type="hidden" name="check_in" id="checkIn" value="{{ request('check_in') }}">
            <input type="hidden" name="check_out" id="checkOut" value="{{ request('check_out') }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold">تعداد مهمان</label>
            <select name="guests" class="form-select form-select-sm">
                @for($i = 1; $i <= 10; $i++)
                    <option value="{{ $i }}" {{ request('guests') == $i ? 'selected' : '' }}>{{ $i }} نفر</option>
                @endfor
            </select>
        </div>
        <div class="col-6 col-md-2">
            <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-search me-1"></i>فیلتر
            </button>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="wheelchair" id="wheelchairFilter"
                    value="1" {{ request('wheelchair') ? 'checked' : '' }}>
                <label class="form-check-label fw-semibold small" for="wheelchairFilter">
                    <i class="bi bi-wheelchair text-primary me-1"></i>فقط اقامتگاه‌های مناسب ویلچر
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" style="font-size:.72rem">دسترسی ویلچر</span>
                </label>
            </div>
        </div>
    </form>
</div>

{{-- Results --}}
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold mb-0">
        <i class="bi bi-building me-2 text-primary"></i>
        {{ $accommodations->total() }} اقامتگاه یافت شد
    </h5>
    @auth
        @if(Auth::user()->discount_percentage > 0)
            <span class="badge badge-veteran px-3 py-2">
                <i class="bi bi-tag-fill me-1"></i>
                تخفیف {{ Auth::user()->discount_percentage }}٪ برای شما اعمال می‌شود
            </span>
        @endif
    @endauth
</div>

@forelse($accommodations as $acc)
    <div class="card mb-3 accommodation-card shadow-sm">
        <div class="row g-0">
            <div class="col-4 col-md-3">
                @php
                    $coverImg = $acc->image
                        ?: (collect($acc->images ?? [])->filter()->first());
                @endphp
                @if($coverImg)
                    <img src="{{ asset('storage/' . $coverImg) }}" class="img-fluid rounded-start h-100 object-fit-cover" alt="{{ $acc->name }}" style="max-height:200px;">
                @else
                    <div class="bg-light d-flex align-items-center justify-content-center h-100 rounded-start" style="min-height:140px">
                        <i class="bi bi-building text-muted" style="font-size:2rem"></i>
                    </div>
                @endif
            </div>
            <div class="col-8 col-md-9">
                <div class="card-body p-2 p-md-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-1 mb-1">
                        <h6 class="card-title fw-bold mb-0">{{ $acc->name }}</h6>
                        <div class="text-end flex-shrink-0">
                            @auth
                                @php $discounted = $acc->price_per_night * (1 - Auth::user()->discount_percentage / 100); @endphp
                                @if(Auth::user()->discount_percentage > 0)
                                    <div class="text-decoration-line-through text-muted" style="font-size:.75rem">{{ number_format($acc->price_per_night) }}</div>
                                    <div class="price-tag">{{ number_format($discounted) }}</div>
                                    <div style="font-size:.7rem" class="text-muted">تومان/شب</div>
                                    <span class="badge bg-danger" style="font-size:.7rem">{{ Auth::user()->discount_percentage }}٪ تخفیف</span>
                                @else
                                    <div class="price-tag">{{ number_format($acc->price_per_night) }}</div>
                                    <div style="font-size:.75rem" class="text-muted">تومان/شب</div>
                                @endif
                            @else
                                <div class="price-tag">{{ number_format($acc->price_per_night) }}</div>
                                <div style="font-size:.75rem" class="text-muted">تومان/شب</div>
                            @endauth
                        </div>
                    </div>
                    <p class="text-muted mb-1" style="font-size:.8rem">
                        <i class="bi bi-geo-alt me-1"></i>{{ $acc->city->province->name }} - {{ $acc->city->name }}
                    </p>
                    <p class="text-muted mb-1 d-none d-sm-block" style="font-size:.8rem">
                        <span class="badge bg-secondary me-1">{{ $acc->typeLabel() }}</span>
                        <i class="bi bi-people me-1"></i>{{ $acc->capacity }} نفر
                        <i class="bi bi-door-open ms-1 me-1"></i>{{ $acc->rooms }} اتاق
                        @if(in_array('مناسب ویلچر', $acc->amenities ?? []))
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">
                                <i class="bi bi-wheelchair me-1"></i>مناسب ویلچر
                            </span>
                        @endif
                    </p>
                    @if($acc->description)
                        <p class="text-muted mb-2 d-none d-md-block" style="font-size:.82rem">{{ \Illuminate\Support\Str::limit($acc->description, 100) }}</p>
                    @endif
                    @if($acc->amenities)
                        <div class="mb-2 d-none d-md-block">
                            @foreach(array_slice($acc->amenities, 0, 4) as $amenity)
                                <span class="badge bg-light text-dark border me-1" style="font-size:.75rem">{{ $amenity }}</span>
                            @endforeach
                        </div>
                    @endif
                    <a href="{{ route('accommodations.show', $acc) }}?check_in={{ request('check_in') }}&check_out={{ request('check_out') }}&guests={{ request('guests', 1) }}"
                       class="btn btn-primary btn-sm">
                        <i class="bi bi-eye me-1"></i><span class="d-none d-sm-inline">مشاهده و </span>رزرو
                    </a>
                </div>
            </div>
        </div>
    </div>
@empty
    <div class="text-center py-5">
        <i class="bi bi-building-x display-4 text-muted"></i>
        <p class="mt-3 text-muted">اقامتگاهی با این مشخصات یافت نشد.</p>
        <a href="{{ route('home') }}" class="btn btn-outline-primary">جستجوی مجدد</a>
    </div>
@endforelse

{{ $accommodations->appends(request()->query())->links() }}

@endsection

@push('scripts')
<script>
$('.select2-basic').select2({ theme: 'bootstrap-5', language: 'fa', width: '100%' });

$('#provinceSelect').on('change', function() {
    const pid = $(this).val();
    const cs = $('#citySelect');
    cs.prop('disabled', true).html('<option value="">در حال بارگذاری...</option>');
    if (!pid) { cs.html('<option value="">همه شهرها</option>').prop('disabled', false); return; }
    $.getJSON(`/api/provinces/${pid}/cities`, function(data) {
        let opts = '<option value="">همه شهرها</option>';
        data.forEach(c => opts += `<option value="${c.id}">${c.name}</option>`);
        cs.html(opts).prop('disabled', false).trigger('change.select2');
    });
});

var ciGreg = $('#checkIn').val();
var coGreg = $('#checkOut').val();
initJalaliRange('#indexCalEl', '#checkIn', '#checkOut', '#indexDateDisplay', ciGreg, coGreg);
</script>
@endpush
