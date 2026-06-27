@extends('layouts.admin')

@section('content')

<div>

<div class="mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <a wire:navigate href="{{ route('admin.room-types.index', $accommodation) }}" class="text-muted small">
        <i class="bi bi-chevron-right me-1"></i>بازگشت به مدیریت اتاق‌های {{ $accommodation->name }}
    </a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-bold"><i class="bi bi-door-open me-2"></i>ویرایش مشخصات اتاق: {{ $roomType->name }}</div>
    <div class="card-body">
        <form action="{{ route('admin.room-types.update', [$accommodation, $roomType]) }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.room_types._form', ['roomType' => $roomType])

            <x-room-type.physical-rooms-section :roomType="$roomType" />

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-warning">
                    <i class="bi bi-check-lg me-1"></i>ذخیره تغییرات
                </button>
                <a wire:navigate href="{{ route('admin.room-types.index', $accommodation) }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

<x-room-type.rates-section
    :accommodation="$accommodation"
    :roomType="$roomType"
    route-prefix="admin.room-types"
/>

</div>

@endsection

