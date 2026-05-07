@extends('layouts.app')

@section('title', 'پروفایل من')

@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card p-4">
            <div class="text-center mb-3">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width:80px;height:80px;font-size:2rem;">
                    <i class="bi bi-person-fill"></i>
                </div>
                <h5 class="mt-3 fw-bold">{{ $user->name ?? 'کاربر' }}</h5>
                <p class="text-muted" dir="ltr">{{ $user->mobile }}</p>
                @if($user->discount_percentage > 0)
                    <span class="badge badge-veteran px-3 py-2">
                        <i class="bi bi-star-fill me-1"></i>
                        {{ $user->veteranLabel() }} | {{ $user->discount_percentage }}% تخفیف
                    </span>
                @else
                    <span class="badge bg-secondary px-3 py-2">کاربر عادی</span>
                @endif
            </div>
            <hr>
            @if($user->national_id_verified_at)
                <div class="d-flex align-items-center text-success small">
                    <i class="bi bi-patch-check-fill me-2 fs-5"></i>
                    <div>
                        <div class="fw-semibold">کد ملی تأیید شده</div>
                        <div dir="ltr">{{ $user->national_id }}</div>
                    </div>
                </div>
            @else
                <p class="text-muted small mb-2">کد ملی تأیید نشده. برای استفاده از تخفیف‌های ویژه، کد ملی خود را تأیید کنید.</p>
                <form action="{{ route('profile.verify-id') }}" method="POST">
                    @csrf
                    <div class="input-group">
                        <input type="text" name="national_id" class="form-control @error('national_id') is-invalid @enderror"
                            placeholder="کد ملی ۱۰ رقمی" maxlength="10" dir="ltr" inputmode="numeric">
                        <button type="submit" class="btn btn-outline-primary">تأیید</button>
                        @error('national_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-calendar-check me-2"></i>رزروهای من</h5>
            @forelse($bookings as $booking)
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="fw-bold mb-1">{{ $booking->accommodation->name }}</h6>
                            <p class="text-muted small mb-1">
                                <i class="bi bi-geo-alt me-1"></i>
                                {{ $booking->accommodation->city->province->name }} - {{ $booking->accommodation->city->name }}
                            </p>
                            <p class="small mb-1">
                                <i class="bi bi-calendar me-1"></i>
                                {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_in))->format('Y/m/d') }}
                                تا
                                {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_out))->format('Y/m/d') }}
                                ({{ $booking->nights }} شب)
                            </p>
                        </div>
                        <span class="badge bg-{{ $booking->statusColor() }} fs-6">{{ $booking->statusLabel() }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="small text-muted">
                            کد رهگیری: <span class="tracking-code text-dark">{{ $booking->tracking_code }}</span>
                        </div>
                        <div class="text-end">
                            <div class="price-tag small">{{ number_format($booking->total_price) }} تومان</div>
                            <a href="{{ route('bookings.show', $booking) }}" class="btn btn-sm btn-outline-primary mt-1">جزئیات</a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-x display-4"></i>
                    <p class="mt-3">هنوز رزروی ثبت نکرده‌اید.</p>
                    <a href="{{ route('home') }}" class="btn btn-primary">جستجوی اقامتگاه</a>
                </div>
            @endforelse
            {{ $bookings->links() }}
        </div>
    </div>
</div>
@endsection
