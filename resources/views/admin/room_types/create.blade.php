@extends('layouts.admin')

@section('pageTitle')
اتاق جدید — {{ $accommodation->name }}
@endsection

@section('content')
<div>

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
