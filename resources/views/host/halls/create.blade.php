@extends('layouts.host')

@section('pageTitle')
سالن جدید — {{ $accommodation->name }}
@endsection

@section('content')
<div>
<div class="card shadow-sm">
    <div class="card-header fw-bold"><i class="bi bi-building me-2"></i>تعریف سالن جدید</div>
    <div class="card-body">
        <form method="POST" action="{{ route('host.halls.store', $accommodation) }}">
            @csrf
            <x-hall.form-fields :hall="null" />
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-success px-4">ثبت سالن</button>
                <a wire:navigate href="{{ route('host.halls.accommodation.index', $accommodation) }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>
</div>
@endsection
