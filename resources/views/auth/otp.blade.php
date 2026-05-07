@extends('layouts.app')

@section('title', 'تأیید کد OTP')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4 mt-5">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock display-4 text-success"></i>
                <h4 class="mt-2 fw-bold">تأیید هویت</h4>
                <p class="text-muted small">
                    کد ۶ رقمی ارسال شده به شماره <strong dir="ltr">{{ $mobile }}</strong> را وارد کنید
                </p>
                <div class="alert alert-info small py-2">
                    <i class="bi bi-info-circle me-1"></i>
                    در حالت آزمایشی، کد OTP در لاگ‌ها ثبت می‌شود:
                    <code>storage/logs/laravel.log</code>
                </div>
            </div>
            <form action="{{ route('auth.otp.verify') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">کد تأیید</label>
                    <input
                        type="text"
                        name="otp"
                        class="form-control form-control-lg text-center @error('otp') is-invalid @enderror"
                        placeholder="_ _ _ _ _ _"
                        maxlength="6"
                        dir="ltr"
                        autofocus
                        inputmode="numeric"
                    >
                    @error('otp')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-success btn-lg w-100">
                    <i class="bi bi-check2-circle me-1"></i> تأیید و ورود
                </button>
            </form>
            <div class="text-center mt-3">
                <a href="{{ route('auth.mobile') }}" class="text-muted small">
                    <i class="bi bi-arrow-right me-1"></i>تغییر شماره موبایل
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
