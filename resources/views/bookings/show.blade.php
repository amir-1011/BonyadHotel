@extends('layouts.app')

@section('title', 'جزئیات رزرو')

@section('content')
<div class="container-xxl px-3 px-lg-4" style="padding-top:32px;padding-bottom:48px;">
<div style="max-width:720px;margin:0 auto;">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 style="font-size:22px;font-weight:700;color:var(--bnb-dark);margin:0;">جزئیات رزرو</h1>
        <a href="{{ route('bookings.index') }}" class="bnb-filter-pill text-decoration-none"><i class="bi bi-arrow-right me-1"></i>رزروهای من</a>
    </div>

    {{-- Tracking code --}}
    @php $statusColors = ['confirmed'=>'#00a699','pending'=>'#ff9900','cancelled'=>'#FF385C']; $sc = $statusColors[$booking->status] ?? '#717171'; @endphp
    <div style="background:{{ $sc }}15;border:1px solid {{ $sc }}40;border-radius:12px;padding:20px 24px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:12px;color:var(--bnb-gray);margin-bottom:4px;">کد رهگیری</div>
            <div style="font-size:20px;font-weight:700;color:var(--bnb-dark);letter-spacing:1px;direction:ltr;">{{ $booking->tracking_code }}</div>
        </div>
        <span style="background:{{ $sc }};color:#fff;border-radius:20px;padding:8px 20px;font-size:14px;font-weight:600;">{{ $booking->statusLabel() }}</span>
    </div>

    {{-- Accommodation info --}}
    <div style="border:1px solid var(--bnb-border);border-radius:12px;padding:20px;margin-bottom:16px;">
        <div style="font-size:12px;color:var(--bnb-gray);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">اطلاعات اقامتگاه</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><div style="font-size:12px;color:var(--bnb-gray);">نام</div><div style="font-size:14px;font-weight:600;color:var(--bnb-dark);">{{ $booking->accommodation->name }}</div></div>
            <div><div style="font-size:12px;color:var(--bnb-gray);">نوع</div><div style="font-size:14px;color:var(--bnb-dark);">{{ $booking->accommodation->typeLabel() }}</div></div>
            <div style="grid-column:1/-1;"><div style="font-size:12px;color:var(--bnb-gray);">موقعیت</div><div style="font-size:14px;color:var(--bnb-dark);">{{ $booking->accommodation->city->province->name }} — {{ $booking->accommodation->city->name }}@if($booking->accommodation->address)، {{ $booking->accommodation->address }}@endif</div></div>
        </div>
    </div>

    {{-- Stay info --}}
    <div style="border:1px solid var(--bnb-border);border-radius:12px;padding:20px;margin-bottom:16px;">
        <div style="font-size:12px;color:var(--bnb-gray);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">اطلاعات اقامت</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <div><div style="font-size:12px;color:var(--bnb-gray);">تاریخ ورود</div><div style="font-size:14px;font-weight:600;color:var(--bnb-dark);">{{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_in))->format('Y/m/d') }}</div></div>
            <div><div style="font-size:12px;color:var(--bnb-gray);">تاریخ خروج</div><div style="font-size:14px;font-weight:600;color:var(--bnb-dark);">{{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_out))->format('Y/m/d') }}</div></div>
            <div><div style="font-size:12px;color:var(--bnb-gray);">مدت اقامت</div><div style="font-size:14px;font-weight:600;color:var(--bnb-dark);">{{ $booking->nights }} شب</div></div>
            <div><div style="font-size:12px;color:var(--bnb-gray);">تعداد مهمان</div><div style="font-size:14px;font-weight:600;color:var(--bnb-dark);">{{ $booking->guests }} نفر</div></div>
        </div>
    </div>

    {{-- Room info --}}
    @if($booking->roomType)
    <div style="border:1px solid var(--bnb-border);border-radius:12px;padding:20px;margin-bottom:16px;">
        <div style="font-size:12px;color:var(--bnb-gray);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">اتاق رزرو شده</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div><div style="font-size:12px;color:var(--bnb-gray);">نوع اتاق</div><div style="font-size:14px;font-weight:600;color:var(--bnb-dark);">{{ $booking->roomType->name }}</div></div>
            <div><div style="font-size:12px;color:var(--bnb-gray);">نوع تخت</div><div style="font-size:14px;color:var(--bnb-dark);">{{ $booking->roomType->bed_type }}</div></div>
            @if($booking->roomRate)
            <div><div style="font-size:12px;color:var(--bnb-gray);">تعرفه</div><div style="font-size:14px;color:var(--bnb-dark);">{{ $booking->roomRate->name }}</div></div>
            <div><div style="font-size:12px;color:var(--bnb-gray);">صبحانه</div><div style="font-size:14px;color:var(--bnb-dark);">@if($booking->roomRate->breakfast_included)<span style="color:green"><i class="bi bi-check-circle-fill me-1"></i>دارد</span>@else<span style="color:var(--bnb-gray)"><i class="bi bi-x-circle me-1"></i>ندارد</span>@endif</div></div>
            <div><div style="font-size:12px;color:var(--bnb-gray);">سیاست لغو</div><div style="font-size:14px;color:var(--bnb-dark);">{{ $booking->roomRate->cancellationLabel() }}</div></div>
            @endif
        </div>
    </div>
    @endif

    {{-- Price details --}}
    <div style="border:1px solid var(--bnb-border);border-radius:12px;padding:20px;margin-bottom:20px;">
        <div style="font-size:12px;color:var(--bnb-gray);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:16px;">جزئیات مالی</div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;"><span>قیمت پایه ({{ $booking->nights }} شب × {{ number_format($booking->accommodation->price_per_night) }} تومان)</span><span>{{ number_format($booking->base_price) }} تومان</span></div>
        @if($booking->discount_percentage > 0)
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;color:var(--bnb-red);"><span>تخفیف {{ $booking->discount_percentage }}٪ ({{ $booking->user->veteranLabel() }})</span><span>− {{ number_format($booking->discount_amount) }} تومان</span></div>
        @endif
        <div style="border-top:1px solid var(--bnb-border);padding-top:12px;margin-top:8px;display:flex;justify-content:space-between;font-size:16px;font-weight:700;color:var(--bnb-dark);"><span>مبلغ نهایی</span><span>{{ number_format($booking->total_price) }} تومان</span></div>
    </div>

    {{-- Actions --}}
    <div class="d-flex gap-2 flex-wrap mb-4">
        @if($booking->status === 'confirmed' && $booking->check_out >= now()->toDateString())
        <form action="{{ route('bookings.cancel', $booking) }}" method="POST" onsubmit="return confirm('آیا از لغو این رزرو مطمئن هستید؟')">
            @csrf
            <button type="submit" class="bnb-filter-pill" style="border-color:var(--bnb-red);color:var(--bnb-red);cursor:pointer;background:none;font-family:var(--bnb-font);"><i class="bi bi-x-circle me-1"></i>لغو رزرو</button>
        </form>
        @endif
        <a href="{{ route('accommodations.show', $booking->accommodation) }}" class="bnb-filter-pill text-decoration-none" target="_blank"><i class="bi bi-building me-1"></i>مشاهده اقامتگاه</a>
    </div>

    {{-- Review section --}}
    @if($canReview)
    <div style="border:1px solid var(--bnb-border);border-radius:12px;padding:24px;" id="review-section">
        @if(session('status'))<div class="bnb-alert bnb-alert-success mb-4"><i class="bi bi-check-circle-fill me-2"></i>{{ session('status') }}</div>@endif
        @if($userReview)
        <h3 style="font-size:16px;font-weight:600;color:var(--bnb-dark);margin-bottom:16px;"><i class="bi bi-star-fill me-2" style="color:var(--bnb-dark);"></i>نظر ثبت‌شده شما</h3>
        <div style="background:var(--bnb-bg-light);border-radius:8px;padding:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <div>@for($s=1;$s<=5;$s++)<i class="bi bi-star{{ $s <= $userReview->rating ? '-fill' : '' }}" style="color:var(--bnb-dark);"></i>@endfor</div>
                <span style="font-size:12px;color:var(--bnb-gray);">{{ $userReview->created_at->diffForHumans() }}</span>
            </div>
            <p style="font-size:14px;color:var(--bnb-dark);margin:0;">{{ $userReview->comment ?? '(بدون متن)' }}</p>
            @if($userReview->host_reply)
            <div style="background:#fff;border-radius:8px;padding:12px;margin-top:12px;border:1px solid var(--bnb-border);">
                <div style="font-size:12px;font-weight:600;color:var(--bnb-dark);margin-bottom:4px;"><i class="bi bi-reply-fill me-1"></i>پاسخ میزبان</div>
                <p style="font-size:13px;color:var(--bnb-gray);margin:0;">{{ $userReview->host_reply }}</p>
            </div>
            @endif
        </div>
        @else
        <h3 style="font-size:16px;font-weight:600;color:var(--bnb-dark);margin-bottom:8px;"><i class="bi bi-star me-2"></i>نظر خود را ثبت کنید</h3>
        <p style="font-size:13px;color:var(--bnb-gray);margin-bottom:20px;">اقامت شما در <strong>{{ $booking->accommodation->name }}</strong> به پایان رسیده. تجربه‌تان را به اشتراک بگذارید.</p>
        @if($errors->any())<div class="bnb-alert bnb-alert-danger mb-4">{{ $errors->first() }}</div>@endif
        <form action="{{ route('reviews.store', $booking->accommodation) }}" method="POST">
            @csrf
            <input type="hidden" name="booking_id" value="{{ $booking->id }}">
            @include('bookings._review_form', ['bookingId' => $booking->id, 'currentRating' => old('rating', 5), 'currentComment' => old('comment', '')])
        </form>
        @endif
    </div>
    @endif

</div>
</div>
@endsection
