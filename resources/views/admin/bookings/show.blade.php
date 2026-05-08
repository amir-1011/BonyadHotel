@extends('layouts.admin')
@section('title', 'جزئیات رزرو')
@section('page-title', 'جزئیات رزرو')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('admin.bookings.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">رزرو {{ $booking->tracking_code }}</h5>
    <span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-calendar-check me-2"></i>اطلاعات رزرو</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">کد پیگیری</span><code>{{ $booking->tracking_code }}</code></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">اقامتگاه</span><strong>{{ $booking->accommodation->name }}</strong></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">شهر</span><span>{{ $booking->accommodation->city->name }}</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تاریخ ورود</span><span>@jalali($booking->check_in)</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تاریخ خروج</span><span>@jalali($booking->check_out)</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تعداد شب</span><span>{{ $booking->nights }}</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تعداد مهمان</span><span>{{ $booking->guests }}</span></li>
                @if($booking->roomType)
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">نوع اتاق</span><span>{{ $booking->roomType->name }}</span></li>
                @endif
                @if($booking->roomRate)
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تعرفه</span><span>{{ $booking->roomRate->name }}</span></li>
                @endif
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">قیمت پایه</span><span>{{ number_format($booking->base_price) }} ت</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تخفیف ({{ $booking->discount_percentage }}%)</span><span class="text-danger">{{ number_format($booking->discount_amount) }} ت-</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="fw-bold">مبلغ نهایی</span><strong class="text-primary">{{ number_format($booking->total_price) }} ت</strong></li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-person me-2"></i>اطلاعات رزرو‌کننده</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">نام</span><span>{{ $booking->user->name ?? '—' }}</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">موبایل</span><code>{{ $booking->user->mobile }}</code></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">نوع ایثارگر</span><span>{{ $booking->user->veteranLabel() }}</span></li>
            </ul>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-gear me-2"></i>تغییر وضعیت</div>
            <div class="card-body">
                <form action="{{ route('admin.bookings.status', $booking) }}" method="POST">
                    @csrf
                    <div class="d-flex gap-2">
                        <select name="status" class="form-select form-select-sm">
                            <option value="pending" {{ $booking->status=='pending'?'selected':'' }}>در انتظار</option>
                            <option value="confirmed" {{ $booking->status=='confirmed'?'selected':'' }}>تأیید شده</option>
                            <option value="cancelled" {{ $booking->status=='cancelled'?'selected':'' }}>لغو شده</option>
                        </select>
                        <button class="btn btn-sm btn-primary">ذخیره</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
