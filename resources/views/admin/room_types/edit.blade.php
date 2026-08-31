@extends('layouts.admin')

@section('pageTitle')
ویرایش اتاق — {{ $roomType->name }}
@endsection

@section('content')

<div>

<div class="card shadow-sm mb-3">
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

