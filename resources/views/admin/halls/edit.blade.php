@extends('layouts.admin')

@section('pageTitle')
ویرایش سالن — {{ $hall->name }}
@endsection

@section('content')
<div>
<div class="card shadow-sm">
    <div class="card-header fw-bold"><i class="bi bi-building me-2"></i>ویرایش سالن: {{ $hall->name }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.halls.update', [$accommodation, $hall]) }}">
            @csrf @method('PUT')
            <x-hall.form-fields :hall="$hall" />
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-warning px-4">ذخیره تغییرات</button>
                <a wire:navigate href="{{ route('admin.halls.accommodation.index', $accommodation) }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
