<div>

<div class="container-xxl px-3 px-lg-4" style="padding-top:32px;padding-bottom:48px;">
<div style="max-width:720px;margin:0 auto;">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 style="font-size:22px;font-weight:700;color:var(--bnb-dark);margin:0;">ثبت رزرو</h1>
        <a href="{{ route('accommodations.show', $accommodation) }}" class="bnb-filter-pill text-decoration-none" wire:navigate><i class="bi bi-arrow-right me-1"></i>بازگشت به اقامتگاه</a>
    </div>

    {{-- Accommodation summary --}}
    <div style="border:1px solid var(--bnb-border);border-radius:12px;padding:20px;margin-bottom:20px;background:#fff;">
        <div class="d-flex gap-3 align-items-center">
            @if($accommodation->image)
            <img src="{{ asset('storage/'.$accommodation->image) }}" alt="{{ $accommodation->name }}" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
            @endif
            <div>
                <div style="font-size:16px;font-weight:700;color:var(--bnb-dark);">{{ $accommodation->name }}</div>
                <div style="font-size:13px;color:var(--bnb-gray);"><i class="bi bi-geo-alt me-1"></i>{{ $accommodation->city->province->name ?? '' }} — {{ $accommodation->city->name ?? '' }}</div>
                <div style="font-size:13px;color:var(--bnb-gray);"><i class="bi bi-house me-1"></i>{{ $accommodation->typeLabel() }} &middot; ظرفیت {{ $accommodation->capacity }} نفر</div>
                <div style="font-size:14px;font-weight:600;color:var(--bnb-dark);margin-top:4px;">از {{ \App\Support\PdfPersian::toPersianDigits(number_format($accommodation->price_per_night)) }} ریال / شب</div>
            </div>
        </div>
    </div>

    {{-- Booking form --}}
    <div style="border:1px solid var(--bnb-border);border-radius:12px;padding:24px;background:#fff;">
        <h5 style="font-size:16px;font-weight:700;color:var(--bnb-dark);margin-bottom:20px;">اطلاعات رزرو</h5>

        @if($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0 pe-3">
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label style="font-size:13px;font-weight:600;color:var(--bnb-dark);display:block;margin-bottom:6px;">تاریخ ورود (میلادی)</label>
                <input type="date" wire:model.live="checkIn" class="form-control @error('checkIn') is-invalid @enderror"
                       min="{{ now()->toDateString() }}" value="{{ $checkIn }}">
                @error('checkIn')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label style="font-size:13px;font-weight:600;color:var(--bnb-dark);display:block;margin-bottom:6px;">تاریخ خروج (میلادی)</label>
                <input type="date" wire:model.live="checkOut" class="form-control @error('checkOut') is-invalid @enderror"
                       min="{{ $checkIn ?: now()->addDay()->toDateString() }}" value="{{ $checkOut }}">
                @error('checkOut')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label style="font-size:13px;font-weight:600;color:var(--bnb-dark);display:block;margin-bottom:6px;">تعداد مهمان</label>
                <select wire:model.live="guests" class="form-select @error('guests') is-invalid @enderror">
                    @for($i = 1; $i <= $accommodation->capacity; $i++)
                    <option value="{{ $i }}" {{ $guests == $i ? 'selected' : '' }}>{{ $i }} نفر</option>
                    @endfor
                </select>
                @error('guests')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        {{-- Price estimate --}}
        @if($checkIn && $checkOut && $checkIn < $checkOut)
        @php
            $nights = (int)(new \DateTime($checkIn))->diff(new \DateTime($checkOut))->days;
            $base   = $nights * $accommodation->price_per_night;
            $user   = auth()->user();
            $disc   = $user ? (int)round($base * $user->discount_percentage / 100) : 0;
            $subtotal = $base - $disc;
            $commission = $subtotal > 0 ? (int) config('platform_commission.fixed_amount', 50_000) : 0;
            $total  = $subtotal + $commission;
        @endphp
        <div style="background:var(--bnb-light);border-radius:10px;padding:16px;margin-bottom:20px;">
            <div style="font-size:13px;font-weight:700;color:var(--bnb-dark);margin-bottom:10px;">خلاصه هزینه</div>
            <div class="d-flex justify-content-between mb-1" style="font-size:13px;">
                <span>{{ $nights }} شب × {{ \App\Support\PdfPersian::toPersianDigits(number_format($accommodation->price_per_night)) }} ریال</span>
                <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($base)) }} ریال</span>
            </div>
            @if($disc > 0)
            <div class="d-flex justify-content-between mb-1" style="font-size:13px;color:var(--bnb-red);">
                <span>تخفیف {{ $user->discount_percentage }}٪</span>
                <span>− {{ \App\Support\PdfPersian::toPersianDigits(number_format($disc)) }} ریال</span>
            </div>
            @endif
            @if($commission > 0)
            <div class="d-flex justify-content-between mb-1" style="font-size:13px;">
                <span>کارمزد سامانه</span>
                <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($commission)) }} ریال</span>
            </div>
            @endif
            <div class="d-flex justify-content-between" style="font-size:15px;font-weight:700;color:var(--bnb-dark);border-top:1px solid var(--bnb-border);padding-top:10px;margin-top:6px;">
                <span>مبلغ نهایی</span>
                <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($total)) }} ریال</span>
            </div>
        </div>
        @endif

        <button wire:click="store" wire:loading.attr="disabled" class="btn btn-dark w-100" style="border-radius:10px;padding:14px;font-size:15px;font-weight:600;">
            <span wire:loading.remove><i class="bi bi-check-circle me-2"></i>تأیید و ثبت رزرو</span>
            <span wire:loading><i class="bi bi-hourglass-split me-2"></i>در حال پردازش...</span>
        </button>
    </div>

</div>
</div>

</div>
