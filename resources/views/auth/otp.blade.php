@extends('layouts.app')

@section('title', 'تأیید کد OTP')

@section('content')
<div style="min-height:calc(100vh - 130px);display:flex;align-items:center;justify-content:center;padding:40px 16px;">
    <div style="width:100%;max-width:440px;">
        <div style="text-align:center;margin-bottom:28px;">
            <a href="{{ route('home') }}" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><path fill="#FF385C" d="M16 1C9.4 1 4 7.4 4 15.2c0 4.8 2.4 9.2 6 12.2L16 31l6-3.6c3.6-3 6-7.4 6-12.2C28 7.4 22.6 1 16 1zm0 22c-4.4 0-8-3.6-8-8s3.6-8 8-8 8 3.6 8 8-3.6 8-8 8z"/></svg>
                <span style="font-size:22px;font-weight:700;color:#FF385C;">بنیاد</span>
            </a>
        </div>
        <div style="border:1px solid var(--bnb-border);border-radius:12px;padding:32px;background:#fff;box-shadow:0 4px 20px rgba(0,0,0,.06);">
            <h2 style="font-size:20px;font-weight:700;color:var(--bnb-dark);margin-bottom:6px;">تأیید شماره موبایل</h2>
            <p style="font-size:14px;color:var(--bnb-gray);margin-bottom:4px;">کد ۶ رقمی ارسال شده به</p>
            <p style="font-size:16px;font-weight:700;color:var(--bnb-dark);direction:ltr;margin-bottom:20px;">{{ $mobile }}</p>
            <div class="bnb-alert bnb-alert-success" style="margin-bottom:20px;font-size:12px;">
                <i class="bi bi-info-circle me-1"></i>کد تأیید به‌منظور نسخه تست از 1 تا 6 تعیین شده است.
            </div>
            @if($errors->any())
            <div class="bnb-alert bnb-alert-danger mb-4">
                @foreach($errors->all() as $err)<div>{{ $err }}</div>@endforeach
            </div>
            @endif
            <form action="{{ route('auth.otp.verify') }}" method="POST">
                @csrf
                <div style="margin-bottom:16px;">
                    <label class="bnb-label">کد تأیید</label>
                    <input type="text" name="otp"
                           class="bnb-select @error('otp') is-invalid @enderror"
                           placeholder="_ _ _ _ _ _"
                           maxlength="6" dir="ltr" autofocus inputmode="numeric"
                           style="text-align:center;font-size:24px;letter-spacing:8px;font-weight:700;">
                    @error('otp')<div style="color:var(--bnb-red);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
                </div>
                <button type="submit" class="btn-bnb w-100" style="font-size:15px;padding:14px;">
                    <i class="bi bi-check2-circle me-2"></i>تأیید و ورود
                </button>
            </form>
            <div style="text-align:center;margin-top:16px;">
                <a href="{{ route('auth.mobile') }}" style="font-size:13px;color:var(--bnb-gray);text-decoration:underline;">
                    <i class="bi bi-arrow-right me-1"></i>تغییر شماره موبایل
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
