@extends('layouts.host')

@section('pageTitle')
اتاق جدید — {{ $accommodation->name }}
@endsection

@section('content')

<div>

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
                <a wire:navigate href="{{ route('host.room-types.index', $accommodation) }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

</div>

@endsection

