@extends('layouts.app')

@section('title', 'تکمیل پروفایل')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card p-4 mt-4">
            <div class="text-center mb-4">
                <i class="bi bi-person-badge display-4 text-primary"></i>
                <h4 class="mt-2 fw-bold">تکمیل اطلاعات</h4>
                <p class="text-muted small">برای استفاده از تخفیف‌های ویژه، کد ملی خود را وارد کنید</p>
            </div>

            <div class="alert alert-info small">
                <i class="bi bi-shield-check me-1"></i>
                <strong>کدهای ملی آزمایشی برای تخفیف:</strong>
                <ul class="mb-0 mt-1">
                    <li><code>1110000002</code> - خانواده شهید (۵۰٪)</li>
                    <li><code>2220000004</code> - جانباز ۲۵-۴۹٪ (۳۰٪)</li>
                    <li><code>3330000006</code> - جانباز ۵۰-۶۹٪ (۴۰٪)</li>
                    <li><code>4440000008</code> - جانباز ۷۰٪ و بالاتر (۵۰٪)</li>
                    <li><code>5550000001</code> - خانواده آزاده (۴۰٪)</li>
                    <li><code>0012345678</code> - کاربر عادی (بدون تخفیف)</li>
                </ul>
            </div>

            <form action="{{ route('profile.setup.save') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-semibold">نام و نام خانوادگی</label>
                    <input
                        type="text"
                        name="name"
                        class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name') }}"
                        placeholder="مثال: علی محمدی"
                        autofocus
                    >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">کد ملی</label>
                    <input
                        type="text"
                        name="national_id"
                        class="form-control @error('national_id') is-invalid @enderror"
                        value="{{ old('national_id') }}"
                        placeholder="۱۰ رقم"
                        maxlength="10"
                        dir="ltr"
                        inputmode="numeric"
                    >
                    @error('national_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-check2-all me-1"></i> تأیید و ادامه
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
