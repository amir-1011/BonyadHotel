@extends('layouts.host')
@section('title', 'جزئیات رزرو')
@section('page-title', 'جزئیات رزرو')

@section('content')
<div class="d-flex align-items-center gap-2 mb-3">
    <a href="{{ route('host.bookings.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">رزرو {{ $booking->tracking_code }}</h5>
    <span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-calendar me-2"></i>اطلاعات رزرو</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">اقامتگاه</span><strong>{{ $booking->accommodation->name }}</strong></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تاریخ ورود</span><span>@jalali($booking->check_in)</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تاریخ خروج</span><span>@jalali($booking->check_out)</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تعداد شب</span><span>{{ $booking->nights }}</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تعداد مهمان</span><span>{{ $booking->guests }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="fw-bold">مبلغ کل</span><strong class="text-success">{{ number_format($booking->total_price) }} ت</strong></li>
            </ul>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-person me-2"></i>مهمان</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">نام</span><span>{{ $booking->user->name ?? '—' }}</span></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">موبایل</span><code>{{ $booking->user->mobile }}</code></li>
                <li class="list-group-item d-flex justify-content-between small"><span class="text-muted">تخفیف اعمال شده</span><span>{{ $booking->discount_percentage }}%</span></li>
            </ul>
        </div>

        @if($booking->status === 'pending')
        <div class="card shadow-sm mt-3">
            <div class="card-body d-flex gap-2">
                <form action="{{ route('host.bookings.confirm', $booking) }}" method="POST" class="flex-fill">
                    @csrf
                    <button class="btn btn-success w-100"><i class="bi bi-check-circle me-1"></i>تأیید رزرو</button>
                </form>
                <form action="{{ route('host.bookings.cancel', $booking) }}" method="POST" class="flex-fill" onsubmit="return confirm('لغو شود؟')">
                    @csrf
                    <button class="btn btn-danger w-100"><i class="bi bi-x-circle me-1"></i>لغو رزرو</button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
