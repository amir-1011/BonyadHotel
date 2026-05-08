@extends('layouts.app')

@section('title', 'پروفایل من')

@section('content')
<div class="container-fluid px-3 px-lg-5" style="padding-top:32px;padding-bottom:48px;">
<h1 style="font-size:22px;font-weight:700;color:var(--bnb-dark);margin-bottom:28px;">پروفایل من</h1>

<div style="display:grid;grid-template-columns:300px 1fr;gap:32px;align-items:start;">

    {{-- Left: User card --}}
    <div style="border:1px solid var(--bnb-border);border-radius:12px;padding:24px;text-align:center;position:sticky;top:90px;">
        <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--bnb-red),#E31C5F);display:flex;align-items:center;justify-content:center;font-size:2rem;color:#fff;font-weight:700;margin:0 auto 12px;">
            {{ mb_substr($user->name ?? 'م', 0, 1) }}
        </div>
        <h2 style="font-size:18px;font-weight:700;color:var(--bnb-dark);margin-bottom:4px;">{{ $user->name ?? 'کاربر' }}</h2>
        <p style="font-size:14px;color:var(--bnb-gray);direction:ltr;margin-bottom:12px;">{{ $user->mobile }}</p>
        @if($user->discount_percentage > 0)
        <div style="background:var(--bnb-red)15;border:1px solid var(--bnb-red)40;border-radius:20px;padding:6px 16px;font-size:13px;color:var(--bnb-red);font-weight:600;display:inline-block;">
            <i class="bi bi-star-fill me-1"></i>{{ $user->veteranLabel() }} — {{ $user->discount_percentage }}٪ تخفیف
        </div>
        @else
        <div style="background:var(--bnb-bg-light);border:1px solid var(--bnb-border);border-radius:20px;padding:6px 16px;font-size:13px;color:var(--bnb-gray);display:inline-block;">
            <i class="bi bi-person me-1"></i>کاربر عادی
        </div>
        @endif

        <div style="border-top:1px solid var(--bnb-border);margin-top:20px;padding-top:20px;">
            @if($user->national_id_verified_at)
            <div style="display:flex;align-items:center;gap:10px;color:green;font-size:14px;">
                <i class="bi bi-patch-check-fill" style="font-size:20px;"></i>
                <div style="text-align:right;"><div style="font-weight:600;">کد ملی تأیید شده</div><div style="direction:ltr;font-size:12px;color:var(--bnb-gray);">{{ $user->national_id }}</div></div>
            </div>
            @else
            <p style="font-size:13px;color:var(--bnb-gray);margin-bottom:12px;">کد ملی خود را برای استفاده از تخفیف‌های ویژه تأیید کنید.</p>
            <form action="{{ route('profile.verify-id') }}" method="POST">
                @csrf
                <div class="d-flex gap-2">
                    <input type="text" name="national_id" class="bnb-select @error('national_id') is-invalid @enderror"
                           placeholder="کد ملی ۱۰ رقمی" maxlength="10" dir="ltr" inputmode="numeric" style="flex:1;">
                    <button type="submit" class="btn-bnb" style="white-space:nowrap;padding:10px 16px;">تأیید</button>
                </div>
                @error('national_id')<div style="color:var(--bnb-red);font-size:12px;margin-top:4px;">{{ $message }}</div>@enderror
            </form>
            @endif
        </div>
    </div>

    {{-- Right: Bookings --}}
    <div>
        <h2 style="font-size:18px;font-weight:700;color:var(--bnb-dark);margin-bottom:20px;">رزروهای من</h2>
        @forelse($bookings as $booking)
        <div style="border:1px solid var(--bnb-border);border-radius:12px;padding:20px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:12px;" data-aos="fade-up">
            <div>
                <div style="font-size:15px;font-weight:600;color:var(--bnb-dark);margin-bottom:4px;">{{ $booking->accommodation->name }}</div>
                <div style="font-size:13px;color:var(--bnb-gray);margin-bottom:2px;"><i class="bi bi-geo-alt me-1"></i>{{ $booking->accommodation->city->province->name }} — {{ $booking->accommodation->city->name }}</div>
                <div style="font-size:13px;color:var(--bnb-gray);margin-bottom:4px;">
                    <i class="bi bi-calendar me-1"></i>
                    {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_in))->format('Y/m/d') }}
                    تا
                    {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_out))->format('Y/m/d') }}
                    ({{ $booking->nights }} شب)
                </div>
                <div style="font-size:12px;color:var(--bnb-gray);"><i class="bi bi-hash me-1"></i>{{ $booking->tracking_code }}</div>
            </div>
            <div style="text-align:left;">
                @php $sc2 = ['confirmed'=>'#00a699','pending'=>'#ff9900','cancelled'=>'#FF385C'][$booking->status] ?? '#717171'; @endphp
                <span style="display:block;background:{{ $sc2 }}15;color:{{ $sc2 }};border:1px solid {{ $sc2 }}40;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600;margin-bottom:8px;">{{ $booking->statusLabel() }}</span>
                <div style="font-size:16px;font-weight:700;color:var(--bnb-dark);">{{ number_format($booking->total_price) }} <span style="font-size:12px;font-weight:400;color:var(--bnb-gray);">تومان</span></div>
                <a href="{{ route('bookings.show', $booking) }}" class="bnb-filter-pill text-decoration-none d-block text-center mt-2" style="font-size:12px;">جزئیات</a>
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:40px;color:var(--bnb-gray);">
            <div style="font-size:48px;margin-bottom:12px;">📅</div>
            <p>هنوز رزروی ثبت نکرده‌اید.</p>
            <a href="{{ route('home') }}" class="btn-bnb" style="display:inline-block;text-decoration:none;">جستجوی اقامتگاه</a>
        </div>
        @endforelse
        <div class="mt-3">{{ $bookings->links() }}</div>
    </div>

</div>
</div>
@endsection
