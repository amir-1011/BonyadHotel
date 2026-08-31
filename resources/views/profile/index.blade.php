

@push('styles')
<style>
/* ── Profile page ─────────────────────────────────────── */
.prf-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 28px;
    align-items: start;
    padding-top: 28px;
    padding-bottom: 60px;
}
.prf-left-col {
    position: sticky;
    top: 135px;
    align-self: start;
}
@media (max-width: 767px) {
    .prf-layout {
        grid-template-columns: 1fr;
        gap: 16px;
        padding-top: 16px;
        padding-bottom: 48px;
    }
    .prf-left-col {
        position: static;
    }
}

/* Cards */
.prf-card {
    background: #fff;
    border: 1px solid var(--bnb-border);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.prf-card-body { padding: 20px; }

/* User card hero */
.prf-hero {
    background: linear-gradient(135deg, var(--bnb-red) 0%, #E31C5F 100%);
    padding: 28px 20px 20px;
    text-align: center;
    color: #fff;
}
.prf-avatar {
    width: 72px; height: 72px;
    border-radius: 50%;
    background: rgba(255,255,255,.25);
    border: 3px solid rgba(255,255,255,.6);
    display: flex; align-items: center; justify-content: center;
    font-size: 2rem; font-weight: 700;
    margin: 0 auto 10px;
}
.prf-name { font-size: 17px; font-weight: 700; margin-bottom: 2px; }
.prf-mobile { font-size: 13px; opacity: .85; direction: ltr; }

/* Badge pill */
.prf-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 14px; border-radius: 20px;
    font-size: 12px; font-weight: 600;
    margin-top: 10px;
}
.prf-badge.gold { background: rgba(255,255,255,.2); color: #fff; border: 1px solid rgba(255,255,255,.4); }
.prf-badge.plain { background: var(--bnb-bg-light); color: var(--bnb-gray); border: 1px solid var(--bnb-border); margin-top: 0; }

/* Info row */
.prf-info-row {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--bnb-border);
}
.prf-info-row:last-child { border-bottom: none; padding-bottom: 0; }
.prf-info-icon {
    width: 38px; height: 38px; border-radius: 10px;
    background: var(--bnb-bg-light);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; color: var(--bnb-gray); flex-shrink: 0;
}
.prf-info-icon.green { background: #e6f9f4; color: #00a699; }
.prf-info-label { font-size: 11px; color: var(--bnb-gray); margin-bottom: 2px; }
.prf-info-val { font-size: 14px; font-weight: 600; color: var(--bnb-dark); direction: ltr; }

/* National ID form */
.prf-nid-hint { font-size: 12px; color: var(--bnb-gray); margin-bottom: 10px; }

/* Section heading */
.prf-section-title {
    font-size: 17px; font-weight: 700; color: var(--bnb-dark);
    margin-bottom: 14px;
    display: flex; align-items: center; gap: 8px;
}

/* Booking card */
.prf-booking-card {
    background: #fff;
    border: 1px solid var(--bnb-border);
    border-radius: 14px;
    margin-bottom: 12px;
    overflow: hidden;
    box-shadow: 0 1px 6px rgba(0,0,0,.05);
    transition: box-shadow .2s;
}
.prf-booking-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.1); }
.prf-booking-top {
    display: flex; align-items: start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 16px 12px;
}
.prf-booking-meta {
    display: flex; flex-wrap: wrap; gap: 6px;
    padding: 0 16px 14px;
    border-top: 1px solid var(--bnb-border);
}
.prf-meta-chip {
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--bnb-bg-light);
    border-radius: 8px; padding: 4px 10px;
    font-size: 12px; color: var(--bnb-gray);
}
.prf-status-dot {
    width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
}

/* Empty state */
.prf-empty {
    text-align: center; padding: 48px 24px; color: var(--bnb-gray);
}
.prf-empty-icon { font-size: 46px; margin-bottom: 12px; }
</style>
@endpush
<div>

<div class="container-xxl px-3 px-lg-4">
<div class="prf-layout">

    {{-- ── LEFT: User Card ── --}}
    <div class="prf-left-col">

        {{-- Identity card --}}
        <div class="prf-card" style="margin-bottom:16px;">
            <div class="prf-hero">
                <div class="prf-avatar">{{ mb_substr($user->name ?? 'م', 0, 1) }}</div>
                <div class="prf-name">{{ $user->name ?? 'کاربر' }}</div>
                <div class="prf-mobile">{{ $user->mobile }}</div>
                @if($user->discount_percentage > 0)
                <span class="prf-badge gold"><i class="bi bi-star-fill"></i>{{ $user->veteranLabel() }} · {{ $user->discount_percentage }}٪ تخفیف</span>
                @endif
            </div>
            <div class="prf-card-body">
                @if(!$user->discount_percentage)
                <div class="d-flex justify-content-center mb-3">
                    <span class="prf-badge plain"><i class="bi bi-person"></i>کاربر عادی</span>
                </div>
                @endif

                {{-- National ID section --}}
                @if($user->national_id_verified_at)
                <div class="prf-info-row">
                    <div class="prf-info-icon green"><i class="bi bi-patch-check-fill"></i></div>
                    <div>
                        <div class="prf-info-label">کد ملی</div>
                        <div class="prf-info-val">{{ $user->national_id }} <span style="font-size:11px;color:#00a699;font-weight:600;">✓ تأیید شده</span></div>
                    </div>
                </div>
                @else
                <div class="prf-nid-hint"><i class="bi bi-shield-exclamation me-1"></i>کد ملی را تأیید کنید تا از تخفیف‌های ویژه بهره‌مند شوید.</div>
                <form action="{{ route('profile.verify-id') }}" method="POST">
                    @csrf
                    <div class="d-flex gap-2">
                        <input type="text" name="national_id"
                               class="bnb-select @error('national_id') is-invalid @enderror"
                               placeholder="کد ملی ۱۰ رقمی" maxlength="10" dir="ltr" inputmode="numeric" style="flex:1;font-size:14px;">
                        <button type="submit" class="btn-bnb" style="white-space:nowrap;padding:10px 16px;font-size:13px;">تأیید</button>
                    </div>
                    @error('national_id')<div style="color:var(--bnb-red);font-size:12px;margin-top:6px;">{{ $message }}</div>@enderror
                </form>
                @endif
            </div>
        </div>

        @if($user->hasAccountingProfile())
        <div class="prf-card" style="margin-bottom:16px;">
            <div class="prf-card-body">
                <x-profile.accounting-code-card :user="$user" variant="compact" />
            </div>
        </div>
        @endif

        {{-- Quick stats --}}
        <div class="prf-card">
            <div class="prf-card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="text-align:center;padding:14px 8px;background:var(--bnb-bg-light);border-radius:12px;">
                        <div style="font-size:24px;font-weight:700;color:var(--bnb-red);">{{ $bookings->total() }}</div>
                        <div style="font-size:12px;color:var(--bnb-gray);margin-top:2px;">رزرو کل</div>
                    </div>
                    <div style="text-align:center;padding:14px 8px;background:var(--bnb-bg-light);border-radius:12px;">
                        <div style="font-size:24px;font-weight:700;color:#00a699;">{{ $bookings->where('status','confirmed')->count() }}</div>
                        <div style="font-size:12px;color:var(--bnb-gray);margin-top:2px;">تأیید شده</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="prf-card" style="margin-top:16px;">
            <div class="prf-card-body" style="display:flex;flex-direction:column;gap:10px;">

                @if(Auth::user()->hasRole('admin'))
                <a href="{{ route('admin.dashboard') }}" wire:navigate
                   style="display:flex;align-items:center;gap:10px;padding:11px 14px;background:var(--bnb-bg-light);border-radius:10px;text-decoration:none;color:var(--bnb-dark);font-size:14px;font-weight:600;transition:background .15s;"
                   onmouseover="this.style.background='#ffe8ee'" onmouseout="this.style.background='var(--bnb-bg-light)'">
                    <i class="bi bi-shield-lock-fill" style="color:var(--bnb-red);font-size:17px;"></i>
                    پنل مدیریت
                    <i class="bi bi-chevron-left ms-auto" style="font-size:11px;color:var(--bnb-gray);"></i>
                </a>
                @elseif(Auth::user()->hasRole('host'))
                <a href="{{ route('host.dashboard') }}" wire:navigate
                   style="display:flex;align-items:center;gap:10px;padding:11px 14px;background:var(--bnb-bg-light);border-radius:10px;text-decoration:none;color:var(--bnb-dark);font-size:14px;font-weight:600;transition:background .15s;"
                   onmouseover="this.style.background='#ffe8ee'" onmouseout="this.style.background='var(--bnb-bg-light)'">
                    <i class="bi bi-house-gear-fill" style="color:var(--bnb-red);font-size:17px;"></i>
                    پنل میزبان
                    <i class="bi bi-chevron-left ms-auto" style="font-size:11px;color:var(--bnb-gray);"></i>
                </a>
                @endif

                <form action="{{ route('auth.logout') }}" method="POST">
                    @csrf
                    <button type="submit"
                            style="width:100%;display:flex;align-items:center;gap:10px;padding:11px 14px;background:rgba(255,56,92,.06);border:1px solid rgba(255,56,92,.2);border-radius:10px;color:var(--bnb-red);font-size:14px;font-weight:600;cursor:pointer;font-family:var(--bnb-font);transition:background .15s;"
                            onmouseover="this.style.background='rgba(255,56,92,.12)'" onmouseout="this.style.background='rgba(255,56,92,.06)'">
                        <i class="bi bi-box-arrow-right" style="font-size:17px;"></i>
                        خروج از حساب
                    </button>
                </form>

            </div>
        </div>
    </div>

    {{-- ── RIGHT: Bookings ── --}}
    <div>
        <div class="prf-section-title">
            <i class="bi bi-calendar2-check" style="color:var(--bnb-red);"></i>
            رزروهای من
        </div>

        @forelse($bookings as $booking)
        @php
            $statusColors = ['confirmed'=>'#00a699','pending'=>'#ff9900','cancelled'=>'#FF385C'];
            $sc = $statusColors[$booking->status] ?? '#717171';
        @endphp
        <div class="prf-booking-card">
            <div class="prf-booking-top">
                <div style="flex:1;min-width:0;">
                    <div style="font-size:15px;font-weight:700;color:var(--bnb-dark);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $booking->accommodation->name }}
                    </div>
                    <div style="font-size:13px;color:var(--bnb-gray);">
                        <i class="bi bi-geo-alt me-1"></i>{{ $booking->accommodation->city->province->name }}، {{ $booking->accommodation->city->name }}
                    </div>
                </div>
                <div style="text-align:left;flex-shrink:0;">
                    <span style="display:inline-flex;align-items:center;gap:5px;background:{{ $sc }}18;color:{{ $sc }};border:1px solid {{ $sc }}40;border-radius:20px;padding:3px 10px;font-size:12px;font-weight:600;">
                        <span class="prf-status-dot" style="background:{{ $sc }};"></span>
                        {{ $booking->statusLabel() }}
                    </span>
                    <div style="font-size:15px;font-weight:700;color:var(--bnb-dark);margin-top:6px;text-align:left;">
                        {{ \App\Support\PdfPersian::toPersianDigits(number_format($booking->total_price)) }}
                        <span style="font-size:11px;font-weight:400;color:var(--bnb-gray);">ریال</span>
                    </div>
                </div>
            </div>
            <div class="prf-booking-meta">
                <span class="prf-meta-chip">
                    <i class="bi bi-calendar3"></i>
                    {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_in))->format('Y/m/d') }}
                    →
                    {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_out))->format('Y/m/d') }}
                </span>
                <span class="prf-meta-chip"><i class="bi bi-moon"></i>{{ $booking->nights }} شب</span>
                <span class="prf-meta-chip"><i class="bi bi-hash"></i>{{ $booking->tracking_code }}</span>
                <a href="{{ route('bookings.show', $booking) }}" wire:navigate
                   class="prf-meta-chip text-decoration-none"
                   style="color:var(--bnb-red);background:rgba(255,56,92,.07);font-weight:600;">
                    <i class="bi bi-eye"></i>جزئیات
                </a>
            </div>
        </div>
        @empty
        <div class="prf-card prf-empty">
            <div class="prf-empty-icon">📅</div>
            <p style="font-size:15px;font-weight:600;color:var(--bnb-dark);margin-bottom:6px;">رزروی ثبت نشده</p>
            <p style="font-size:13px;margin-bottom:20px;">هنوز هیچ رزروی انجام نداده‌اید.</p>
            <a href="{{ route('home') }}" wire:navigate class="btn-bnb" style="display:inline-block;text-decoration:none;padding:10px 28px;">جستجوی اقامتگاه</a>
        </div>
        @endforelse

        <div class="mt-3">{{ $bookings->links() }}</div>
    </div>

</div>
</div>

</div>