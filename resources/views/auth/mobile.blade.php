@extends('layouts.app')

@section('title', 'ورود با شماره موبایل')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4 mt-5">
            <div class="text-center mb-4">
                <i class="bi bi-phone display-4 text-primary"></i>
                <h4 class="mt-2 fw-bold">ورود / ثبت‌نام</h4>
                <p class="text-muted small">شماره موبایل خود را وارد کنید</p>
            </div>
            <form action="{{ route('auth.send-otp') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">شماره موبایل</label>
                    <input
                        type="tel"
                        name="mobile"
                        class="form-control form-control-lg text-center @error('mobile') is-invalid @enderror"
                        placeholder="09xxxxxxxxx"
                        value="{{ old('mobile') }}"
                        maxlength="11"
                        dir="ltr"
                        autofocus
                    >
                    @error('mobile')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-send me-1"></i> ارسال کد تأیید
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
