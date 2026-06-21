@props([
    'accommodation',
    'roomTypes',
    'mode' => 'public',
    'defaultDiscountPct' => 0,
])
@php
    $isManual = $mode === 'manual';
    $discountPct = $isManual
        ? (int) $defaultDiscountPct
        : (auth()->check() ? (int) auth()->user()->discount_percentage : 0);
@endphp
        {{-- Room Types --}}
        @if($roomTypes->isNotEmpty())
        <div class="{{ $isManual ? 'py-2' : 'py-5 border-bottom' }}" id="sec-rooms"
             x-data="roomsSection()"
             @bnb-guests-changed.window="guestCount = parseInt($event.detail.guests) || 1"
             @bnb-dates-set.window="fetchAllAvail($event.detail.checkIn, $event.detail.checkOut)">
            <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
                <h2 style="font-size:18px;font-weight:700;color:var(--bnb-dark);margin-bottom:0;">انتخاب اتاق</h2>
                <span x-show="loading" class="text-muted" style="font-size:12px;"><span class="spinner-border spinner-border-sm me-1" role="status"></span>بررسی ظرفیت...</span>
            </div>
            <p style="font-size:13px;color:var(--bnb-gray);margin-bottom:20px;">
                @if($isManual)
                    نوع اتاق و نرخ را انتخاب کنید، تاریخ و تعداد نفرات را تأیید کنید. می‌توانید چند اتاق مختلف را در یک بازه زمانی به رزرو اضافه کنید.
                @else
                    پس از انتخاب تاریخ در ویجت رزرو، اتاق موردنظر را رزرو کنید.
                @endif
            </p>
            @foreach($roomTypes as $roomType)
            @if($roomType->rates->isNotEmpty())
            <div class="bnb-room-card"
                 :class="{ 'rt-cap-exceeded': needsHatch({{ $roomType->capacity }}, {{ $roomType->room_count }}, {{ $roomType->id }}) }">
                <div style="display:flex;gap:16px;padding:20px;border-bottom:1px solid var(--bnb-border);flex-wrap:wrap;">
                    @php $rtImages = collect($roomType->images ?? [])->filter()->values(); @endphp
                    @if($rtImages->count() > 0)
                    <img src="{{ asset('storage/' . $rtImages[0]) }}" alt="{{ $roomType->name }}" loading="lazy" decoding="async" style="width:140px;height:100px;object-fit:cover;border-radius:8px;flex-shrink:0;">
                    @endif
                    <div>
                        <div style="font-size:15px;font-weight:600;color:var(--bnb-dark);margin-bottom:8px;" data-rt-name="{{ $roomType->name }}">{{ $roomType->name }}</div>
                        <div style="font-size:13px;color:var(--bnb-gray);display:flex;flex-wrap:wrap;gap:12px;" data-rt-cap="{{ $roomType->capacity }}">
                            @if($roomType->bed_type)<span><i class="bi bi-moon-stars me-1"></i>{{ $roomType->bed_type }}</span>@endif
                            <span><i class="bi bi-people me-1"></i>{{ $roomType->capacity }} نفر</span>
                            @if($roomType->size_sqm)<span><i class="bi bi-aspect-ratio me-1"></i>{{ $roomType->size_sqm }} متر مربع</span>@endif
                            <span>@if($roomType->has_private_bathroom)<i class="bi bi-check-circle-fill text-success me-1"></i>حمام اختصاصی@else<i class="bi bi-x-circle text-muted me-1"></i>بدون حمام اختصاصی@endif</span>
                        </div>
                        {{-- Dynamic badges --}}
                        <div class="d-flex flex-wrap gap-2 mt-1">
                            {{-- Extra capacity badge (floor sleeping available) --}}
                            @if($roomType->extra_capacity)
                            <span style="display:inline-flex;align-items:center;gap:4px;background:#f0fdf4;color:#16a34a;border:1px solid #86efac;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:600;">
                                <i class="bi bi-person-add"></i> کف‌خوابی: تا {{ $roomType->extra_capacity }} نفر ·
                                @if($discountPct > 0)
                                    @php $discExtraCapPrice = round($roomType->extra_capacity_price * (1 - $discountPct / 100)); @endphp
                                    <s style="opacity:.65;">{{ number_format($roomType->extra_capacity_price) }}</s> {{ number_format($discExtraCapPrice) }} تومان/نفر/شب
                                @else
                                    {{ number_format($roomType->extra_capacity_price) }} تومان/نفر/شب
                                @endif
                            </span>
                            @endif
                            {{-- Rooms-needed warning: shows whenever guests exceed per-room capacity OR available rooms --}}
                            <template x-if="needsHatch({{ $roomType->capacity }}, {{ $roomType->room_count }}, {{ $roomType->id }})">
                                <span class="rt-overcap-badge">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <span x-text="'به ' + roomsNeeded({{ $roomType->capacity }}) + ' اتاق نیاز دارید' + (minAvail({{ $roomType->id }}) !== null ? ' (موجود: ' + minAvail({{ $roomType->id }}) + ')' : ' (تعداد اتاق: {{ $roomType->room_count }})')"></span>
                                </span>
                            </template>
                            {{-- Available rooms badge (shown after dates selected, only when not hatched) --}}
                            <template x-if="minAvail({{ $roomType->id }}) !== null && !needsHatch({{ $roomType->capacity }}, {{ $roomType->room_count }}, {{ $roomType->id }})">
                                <span class="rt-avail-badge"
                                      :class="minAvail({{ $roomType->id }}) === 0 ? 'avail-none' : minAvail({{ $roomType->id }}) <= 2 ? 'avail-low' : 'avail-ok'">
                                    <i class="bi bi-door-closed"></i>
                                    <span x-text="minAvail({{ $roomType->id }}) === 0 ? 'تکمیل ظرفیت در این بازه' : minAvail({{ $roomType->id }}) + ' اتاق موجود در این بازه'"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
                @foreach($roomType->rates as $rate)
                <div class="bnb-rate-row">
                    <div>
                        <div style="font-size:14px;font-weight:600;color:var(--bnb-dark);margin-bottom:4px;">{{ $rate->name }}</div>
                        <div style="font-size:12px;color:var(--bnb-gray);display:flex;flex-wrap:wrap;gap:8px;">
                            @if($rate->breakfast_included)<span style="color:green"><i class="bi bi-cup-hot-fill me-1"></i>صبحانه رایگان</span>
                            @elseif($rate->breakfast_price_per_person)<span><i class="bi bi-cup-hot me-1"></i>صبحانه {{ number_format($rate->breakfast_price_per_person) }} تومان/نفر</span>
                            @else<span><i class="bi bi-x me-1"></i>بدون صبحانه</span>@endif
                            @if($rate->cancellation_policy === 'free')<span style="color:green"><i class="bi bi-check-circle-fill me-1"></i>لغو رایگان</span>
                            @else<span style="color:var(--bnb-red)"><i class="bi bi-x-circle me-1"></i>غیرقابل استرداد</span>@endif
                            @if($rate->payment_type === 'pay_at_hotel')<span><i class="bi bi-building me-1"></i>پرداخت در محل</span>
                            @else<span><i class="bi bi-credit-card me-1"></i>پرداخت آنلاین</span>@endif
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div style="text-align:right;white-space:nowrap;">
                            @if($isManual)
                                @if($discountPct > 0)
                                    @php $discRate = round($rate->price_per_night * (1 - $discountPct / 100)); @endphp
                                    <div style="font-size:12px;text-decoration:line-through;color:var(--bnb-gray);">{{ number_format($rate->price_per_night) }}</div>
                                    <div style="font-size:16px;font-weight:700;color:var(--bnb-red);">{{ number_format($discRate) }}</div>
                                @else
                                    <div style="font-size:16px;font-weight:700;color:var(--bnb-dark);">{{ number_format($rate->price_per_night) }}</div>
                                @endif
                            @else
                            @auth
                                @if(Auth::user()->discount_percentage > 0)
                                    @php $discRate = round($rate->price_per_night * (1 - Auth::user()->discount_percentage / 100)); @endphp
                                    <div style="font-size:12px;text-decoration:line-through;color:var(--bnb-gray);">{{ number_format($rate->price_per_night) }}</div>
                                    <div style="font-size:16px;font-weight:700;color:var(--bnb-red);">{{ number_format($discRate) }}</div>
                                @else
                                    <div style="font-size:16px;font-weight:700;color:var(--bnb-dark);">{{ number_format($rate->price_per_night) }}</div>
                                @endif
                            @else
                                <div style="font-size:16px;font-weight:700;color:var(--bnb-dark);">{{ number_format($rate->price_per_night) }}</div>
                            @endauth
                            @endif
                            <div style="font-size:11px;color:var(--bnb-gray);">تومان / شب</div>
                        </div>
                        <div class="bnb-rate-action">
                            @if($isManual)
                            <form class="room-reserve-form" action="#" method="POST" onsubmit="return false;">
                                <input type="hidden" name="room_type_id" value="{{ $roomType->id }}">
                                <input type="hidden" name="room_rate_id" value="{{ $rate->id }}">
                                <input type="hidden" name="check_in" class="rt-check-in">
                                <input type="hidden" name="check_out" class="rt-check-out">
                                <input type="hidden" name="guests" class="rt-guests" value="1">
                                <input type="hidden" name="extra_guests" class="rt-extra-guests" value="0">
                                <input type="hidden" name="bill_full_rooms" class="rt-bill-full-rooms" value="0">
                                @php
                                    $btnDiscPrice = $discountPct > 0 ? round($rate->price_per_night * (1 - $discountPct / 100)) : $rate->price_per_night;
                                    $btnOrigPrice = $rate->price_per_night;
                                @endphp
                                <button type="button" class="btn-bnb" style="white-space:nowrap; width: 100%; min-width: 100px;"
                                    onclick="reserveRoom(this, {{ $btnDiscPrice }}, {{ $btnOrigPrice }}, {{ $roomType->id }}, {{ $roomType->capacity }}, {{ (int)($roomType->extra_capacity ?? 0) }}, {{ (int)($roomType->extra_capacity_price ?? 0) }})">انتخاب</button>
                            </form>
                            @else
                            @auth
                            <form class="room-reserve-form" action="{{ route('bookings.store', $accommodation) }}" method="POST">
                                @csrf
                                <input type="hidden" name="room_type_id" value="{{ $roomType->id }}">
                                <input type="hidden" name="room_rate_id" value="{{ $rate->id }}">
                                <input type="hidden" name="check_in" class="rt-check-in">
                                <input type="hidden" name="check_out" class="rt-check-out">
                                <input type="hidden" name="guests" class="rt-guests" value="1">
                                <input type="hidden" name="extra_guests" class="rt-extra-guests" value="0">
                                <input type="hidden" name="bill_full_rooms" class="rt-bill-full-rooms" value="0">
                                @php
                                    $btnDiscPrice = Auth::user()->discount_percentage > 0 ? round($rate->price_per_night * (1 - Auth::user()->discount_percentage / 100)) : $rate->price_per_night;
                                    $btnOrigPrice = $rate->price_per_night;
                                @endphp
                                <button type="button" class="btn-bnb" style="white-space:nowrap; width: 100%; min-width: 100px;"
                                    onclick="reserveRoom(this, {{ $btnDiscPrice }}, {{ $btnOrigPrice }}, {{ $roomType->id }}, {{ $roomType->capacity }}, {{ (int)($roomType->extra_capacity ?? 0) }}, {{ (int)($roomType->extra_capacity_price ?? 0) }})">رزرو</button>
                            </form>
                            @else
                            <a href="{{ route('auth.mobile') }}" wire:navigate class="btn-bnb" style="text-decoration:none;display:block;white-space:nowrap; width: 100%; text-align: center;">ورود برای رزرو</a>
                            @endauth
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
            @endforeach
        </div>
        @endif
