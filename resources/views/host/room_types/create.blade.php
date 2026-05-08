@extends('layouts.host')
@section('title', 'اتاق جدید — ' . $accommodation->name)
@section('page-title', 'تعریف اتاق جدید')

@push('styles')
<style>
.img-preview-box img { width:100px;height:80px;object-fit:cover;border-radius:8px; }
.amenity-check label { cursor:pointer; }
</style>
@endpush

@section('content')
<div class="mb-3">
    <a href="{{ route('host.room-types.index', $accommodation) }}" class="text-muted small">
        <i class="bi bi-chevron-right me-1"></i>بازگشت به مدیریت اتاق‌های {{ $accommodation->name }}
    </a>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-bold"><i class="bi bi-door-open me-2"></i>تعریف نوع اتاق جدید</div>
    <div class="card-body">
        <form action="{{ route('host.room-types.store', $accommodation) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('host.room_types._form', ['roomType' => null])
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-lg me-1"></i>ذخیره اتاق
                </button>
                <a href="{{ route('host.room-types.index', $accommodation) }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
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
