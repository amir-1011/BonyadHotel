@extends('layouts.admin')

@section('content')
<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('admin.room-types.index', $accommodation) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">اتاق جدید برای {{ $accommodation->name }}</h5>
</div>
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.room-types.store', $accommodation) }}" enctype="multipart/form-data">
            @csrf
            @include('admin.room_types._form', ['roomType' => null])
            <div class="mt-3">
                <button type="submit" class="btn btn-primary px-4">ثبت اتاق</button>
            </div>
        </form>
    </div>
</div>

</div>
@endsection
