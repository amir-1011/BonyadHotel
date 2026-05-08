@extends('layouts.host')
@section('title', 'ویرایش اتاق — ' . $roomType->name)
@section('page-title', 'ویرایش اتاق')

@push('styles')
<style>
.rate-row { transition: background .2s; }
.rate-row:hover { background: #f8f9fa; }
</style>
@endpush

@section('content')
<div class="mb-3">
    <a href="{{ route('host.room-types.index', $accommodation) }}" class="text-muted small">
        <i class="bi bi-chevron-right me-1"></i>بازگشت به مدیریت اتاق‌های {{ $accommodation->name }}
    </a>
</div>

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

{{-- ── Room Type Edit Form ─────────────────────────────────────────────── --}}
<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold"><i class="bi bi-door-open me-2"></i>ویرایش مشخصات اتاق: {{ $roomType->name }}</div>
    <div class="card-body">
        <form action="{{ route('host.room-types.update', [$accommodation, $roomType]) }}"
              method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('host.room_types._form', ['roomType' => $roomType])
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-check-lg me-1"></i>ذخیره تغییرات
                </button>
                <a href="{{ route('host.room-types.index', $accommodation) }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

{{-- ── Rates Management ────────────────────────────────────────────────── --}}
<div class="card shadow-sm">
    <div class="card-header fw-bold d-flex align-items-center justify-content-between">
        <span><i class="bi bi-tags me-2"></i>تعرفه‌های این اتاق</span>
        <button class="btn btn-sm btn-success" type="button"
                data-bs-toggle="collapse" data-bs-target="#addRateForm">
            <i class="bi bi-plus-lg me-1"></i>تعرفه جدید
        </button>
    </div>

    {{-- Add Rate Collapsed Form --}}
    <div class="collapse @if($errors->hasBag('default') || old('price_per_night')) show @endif" id="addRateForm">
        <div class="card-body border-bottom bg-light">
            <h6 class="fw-semibold mb-3 text-success"><i class="bi bi-plus-circle me-2"></i>افزودن تعرفه جدید</h6>
            <form action="{{ route('host.room-types.rates.store', [$accommodation, $roomType]) }}" method="POST">
                @csrf
                @include('host.room_types._rate_form', ['rate' => null])
                <div class="mt-3">
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-check-lg me-1"></i>ذخیره تعرفه
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card-body p-0">
        @if($roomType->rates->isEmpty())
        <div class="text-center py-4 text-muted">
            <i class="bi bi-tags fs-2 mb-2 d-block"></i>
            هنوز تعرفه‌ای تعریف نشده. برای نمایش این اتاق در سایت حداقل یک تعرفه اضافه کنید.
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>نام تعرفه</th>
                        <th>قیمت (تومان/شب)</th>
                        <th>صبحانه</th>
                        <th>لغو</th>
                        <th>پرداخت</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roomType->rates as $rate)
                    <tr class="rate-row">
                        <td class="fw-semibold">{{ $rate->name }}</td>
                        <td class="text-primary fw-bold">{{ number_format($rate->price_per_night) }}</td>
                        <td>
                            @if($rate->breakfast_included)
                                <span class="badge bg-warning text-dark"><i class="bi bi-cup-hot me-1"></i>رایگان</span>
                            @elseif($rate->breakfast_price_per_person)
                                <span class="badge bg-light text-dark border">{{ number_format($rate->breakfast_price_per_person) }} ت/نفر</span>
                            @else
                                <span class="text-muted small">ندارد</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $rate->cancellation_policy === 'free' ? 'success' : 'danger' }}">
                                {{ $rate->cancellationLabel() }}
                            </span>
                        </td>
                        <td class="text-muted small">{{ $rate->paymentLabel() }}</td>
                        <td>
                            <span class="badge bg-{{ $rate->is_active ? 'success' : 'secondary' }}">
                                {{ $rate->is_active ? 'فعال' : 'غیرفعال' }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <button class="btn btn-xs btn-outline-warning" title="ویرایش"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#editRate{{ $rate->id }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="{{ route('host.room-types.rates.destroy', [$accommodation, $roomType, $rate]) }}"
                                      method="POST" onsubmit="return confirm('این تعرفه حذف شود؟')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger" title="حذف">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="7" class="p-0">
                            <div class="collapse" id="editRate{{ $rate->id }}">
                                <div class="bg-light p-3 border-top">
                                    <h6 class="fw-semibold mb-3 text-warning"><i class="bi bi-pencil me-2"></i>ویرایش تعرفه</h6>
                                    <form action="{{ route('host.room-types.rates.update', [$accommodation, $roomType, $rate]) }}"
                                          method="POST">
                                        @csrf @method('PUT')
                                        @include('host.room_types._rate_form', ['rate' => $rate])
                                        <div class="mt-3">
                                            <button type="submit" class="btn btn-warning btn-sm">
                                                <i class="bi bi-check-lg me-1"></i>ذخیره
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('imagesInput')?.addEventListener('change', function () {
    const box = document.getElementById('newImagesPreview');
    box.innerHTML = '';
    Array.from(this.files).forEach(f => {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.style.cssText = 'width:100px;height:80px;object-fit:cover;border-radius:8px';
        box.appendChild(img);
    });
});
</script>
@endpush
