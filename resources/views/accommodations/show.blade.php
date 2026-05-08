@extends('layouts.app')

@section('title', $accommodation->name)

@section('content')
<div class="row g-4">
    {{-- Booking Sidebar: order-first on mobile, second on desktop --}}
    <div class="col-lg-4 order-0 order-lg-1">
        <div class="card p-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-calendar-plus me-2 text-primary"></i>رزرو اقامتگاه</h5>

            @auth
                @if(Auth::user()->discount_percentage > 0)
                    <div class="alert alert-success py-2 small mb-3">
                        <i class="bi bi-tag-fill me-1"></i>
                        تخفیف <strong>{{ Auth::user()->discount_percentage }}٪</strong> برای {{ Auth::user()->veteranLabel() }} اعمال می‌شود.
                    </div>
                @endif
            @endauth

            <form action="{{ route('bookings.store', $accommodation) }}" method="POST" id="bookingForm">
                @csrf
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label fw-semibold small">بازه تاریخ اقامت</label>
                        <div class="range-picker-trigger border rounded-3 px-3 py-2 bg-white d-flex align-items-center justify-content-between
                             @error('check_in') border-danger @enderror @error('check_out') border-danger @enderror"
                             data-bs-toggle="collapse" data-bs-target="#showDateCal">
                            <div id="showDateDisplay"><span class="text-muted">تاریخ ورود را انتخاب کنید</span></div>
                            <i class="bi bi-calendar3 text-primary"></i>
                        </div>
                        <div class="range-picker-phase text-info">کلیک اول: ورود — کلیک دوم: خروج</div>
                        <div class="collapse mt-1" id="showDateCal">
                            <div class="range-picker-cal"><div id="showCalEl"></div></div>
                        </div>
                        <input type="hidden" name="check_in" id="checkIn" value="{{ request('check_in', old('check_in')) }}">
                        <input type="hidden" name="check_out" id="checkOut" value="{{ request('check_out', old('check_out')) }}">
                        @error('check_in')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @error('check_out')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">تعداد مهمان</label>
                        <select name="guests" class="form-select @error('guests') is-invalid @enderror">
                            @for($i = 1; $i <= $accommodation->capacity; $i++)
                                <option value="{{ $i }}" {{ request('guests', 1) == $i ? 'selected' : '' }}>{{ $i }} نفر</option>
                            @endfor
                        </select>
                        @error('guests')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                {{-- Price Preview --}}
                <div id="pricePreview" class="bg-light rounded-3 p-3 my-3 d-none">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>قیمت پایه (<span id="nightsCount">0</span> شب):</span>
                        <span id="basePrice">-</span>
                    </div>
                    @auth
                        @if(Auth::user()->discount_percentage > 0)
                            <div class="d-flex justify-content-between small mb-1 text-success">
                                <span>تخفیف {{ Auth::user()->discount_percentage }}٪:</span>
                                <span id="discountAmount">-</span>
                            </div>
                        @endif
                    @endauth
                    <hr class="my-2">
                    <div class="d-flex justify-content-between fw-bold">
                        <span>مبلغ نهایی:</span>
                        <span id="totalPrice" class="text-primary">-</span>
                    </div>
                </div>

                @auth
                    @if($roomTypes->isNotEmpty())
                    <div class="alert alert-info py-2 mb-2 small">
                        <i class="bi bi-info-circle me-1"></i>
                        پس از انتخاب تاریخ، اتاق موردنظر را از بخش <strong>«انتخاب اتاق»</strong> رزرو کنید.
                    </div>
                    <a href="#roomsSection" class="btn btn-success w-100">
                        <i class="bi bi-door-open me-1"></i>مشاهده اتاق‌ها و رزرو
                    </a>
                    @else
                    <button type="submit" class="btn btn-success w-100 mt-2">
                        <i class="bi bi-check2-circle me-1"></i>ثبت رزرو
                    </button>
                    @endif
                @else
                    <a href="{{ route('auth.mobile') }}" class="btn btn-primary w-100 mt-2">
                        <i class="bi bi-phone me-1"></i>ورود برای رزرو
                    </a>
                @endauth
            </form>
        </div>
    </div>

    {{-- Main Content --}}
    <div class="col-lg-8 order-1 order-lg-0">
        <div class="card p-0 overflow-hidden">
            @php
                $allImages = collect($accommodation->images ?? [])
                    ->filter()
                    ->values();
                if ($accommodation->image && !$allImages->contains($accommodation->image)) {
                    $allImages->prepend($accommodation->image);
                }
            @endphp

            @if($allImages->count() > 1)
                {{-- Bootstrap Carousel --}}
                <div id="accCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        @foreach($allImages as $idx => $img)
                        <button type="button" data-bs-target="#accCarousel" data-bs-slide-to="{{ $idx }}"
                            {{ $idx === 0 ? 'class=active aria-current=true' : '' }}
                            aria-label="تصویر {{ $idx + 1 }}"></button>
                        @endforeach
                    </div>
                    <div class="carousel-inner">
                        @foreach($allImages as $idx => $img)
                        <div class="carousel-item {{ $idx === 0 ? 'active' : '' }}">
                            <img src="{{ asset('storage/' . $img) }}" class="d-block w-100"
                                style="max-height:380px;object-fit:cover;" alt="{{ $accommodation->name }}">
                        </div>
                        @endforeach
                    </div>
                    @if($allImages->count() > 1)
                    <button class="carousel-control-prev" type="button" data-bs-target="#accCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#accCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon"></span>
                    </button>
                    @endif
                </div>
                {{-- Thumbnail strip --}}
                <div class="d-flex gap-2 p-2 bg-light overflow-auto">
                    @foreach($allImages as $idx => $img)
                    <img src="{{ asset('storage/' . $img) }}"
                        style="width:72px;height:54px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid {{ $idx === 0 ? '#0d6efd' : '#dee2e6' }};flex-shrink:0;"
                        onclick="var c=bootstrap.Carousel.getOrCreateInstance(document.getElementById('accCarousel'));c.to({{ $idx }});"
                        alt="thumbnail {{ $idx + 1 }}" data-thumb-idx="{{ $idx }}">
                    @endforeach
                </div>
            @elseif($allImages->count() === 1)
                <img src="{{ asset('storage/' . $allImages[0]) }}" class="img-fluid w-100"
                    style="max-height:380px;object-fit:cover" alt="{{ $accommodation->name }}">
            @else
                <div class="bg-light d-flex align-items-center justify-content-center" style="height:200px">
                    <i class="bi bi-building text-muted" style="font-size:5rem"></i>
                </div>
            @endif
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div class="flex-grow-1">
                        <h4 class="fw-bold mb-1">{{ $accommodation->name }}</h4>
                        <p class="text-muted mb-0 small">
                            <i class="bi bi-geo-alt-fill me-1 text-danger"></i>
                            {{ $accommodation->city->province->name }} - {{ $accommodation->city->name }}
                            @if($accommodation->address)
                                <span class="d-none d-sm-inline"> - {{ $accommodation->address }}</span>
                            @endif
                        </p>
                    </div>
                    <span class="badge bg-primary px-3 py-2">{{ $accommodation->typeLabel() }}</span>
                </div>
                @if(in_array('مناسب ویلچر', $accommodation->amenities ?? []))
                    <div class="alert alert-primary py-2 d-flex align-items-center gap-2 mb-2" style="font-size:.88rem">
                        <i class="bi bi-wheelchair fs-5"></i>
                        <span>این اقامتگاه <strong>مناسب ویلچر</strong> است و دارای رمپ دسترسی، آسانسور و سرویس بهداشتی ویژه معلولان می‌باشد.</span>
                    </div>
                @endif
                <hr>
                <div class="row g-3 text-center mb-4">
                    <div class="col-4">
                        <div class="bg-light rounded-3 p-3">
                            <i class="bi bi-people-fill text-primary fs-4"></i>
                            <div class="fw-bold mt-1">{{ $accommodation->capacity }} نفر</div>
                            <small class="text-muted">ظرفیت</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded-3 p-3">
                            <i class="bi bi-door-open-fill text-success fs-4"></i>
                            <div class="fw-bold mt-1">{{ $accommodation->rooms }} اتاق</div>
                            <small class="text-muted">تعداد اتاق</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="bg-light rounded-3 p-3">
                            <i class="bi bi-cash-stack text-warning fs-4"></i>
                            <div class="fw-bold mt-1">{{ number_format($accommodation->price_per_night) }}</div>
                            <small class="text-muted">تومان/شب</small>
                        </div>
                    </div>
                </div>

                @if($accommodation->description)
                    <h6 class="fw-bold">درباره اقامتگاه</h6>
                    <p class="text-muted">{{ $accommodation->description }}</p>
                @endif

                @if($accommodation->amenities)
                    <h6 class="fw-bold mt-3">امکانات</h6>
                    <div>
                        @foreach($accommodation->amenities as $amenity)
                            <span class="badge bg-light text-dark border me-1 mb-1 px-2 py-2">
                                <i class="bi bi-check-circle-fill text-success me-1"></i>{{ $amenity }}
                            </span>
                        @endforeach
                    </div>
                @endif

                @if($accommodation->lat && $accommodation->lng)
                    <h6 class="fw-bold mt-4">موقعیت روی نقشه</h6>
                    <div id="detailMap" style="height:200px;border-radius:10px"></div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Rooms Section (trip.com style) ────────────────────────────────────── --}}
@if($roomTypes->isNotEmpty())
<div class="mt-4" id="roomsSection">
    <div class="card shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between">
            <h5 class="fw-bold mb-0"><i class="bi bi-door-open me-2 text-primary"></i>انتخاب اتاق</h5>
            <span class="text-muted small">قیمت‌ها بر اساس هر شب اقامت — برای رزرو ابتدا تاریخ‌ها را در بالا انتخاب کنید</span>
        </div>

        @foreach($roomTypes as $roomType)
        @if($roomType->rates->isNotEmpty())
        <div class="border-bottom">
            <div class="row g-0">
                {{-- Left: Room Info --}}
                <div class="col-md-4 p-3 border-end-md">
                    @php $rtImages = collect($roomType->images ?? [])->filter()->values(); @endphp
                    @if($rtImages->count() > 1)
                    <div id="rtCarousel{{ $roomType->id }}" class="carousel slide mb-3" data-bs-ride="false">
                        <div class="carousel-inner rounded-3 overflow-hidden" style="height:180px">
                            @foreach($rtImages as $idx => $img)
                            <div class="carousel-item {{ $idx===0 ? 'active' : '' }}">
                                <img src="{{ asset('storage/'.$img) }}" class="d-block w-100"
                                     style="height:180px;object-fit:cover" alt="{{ $roomType->name }}">
                            </div>
                            @endforeach
                        </div>
                        <button class="carousel-control-prev" type="button"
                                data-bs-target="#rtCarousel{{ $roomType->id }}" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                        </button>
                        <button class="carousel-control-next" type="button"
                                data-bs-target="#rtCarousel{{ $roomType->id }}" data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                        </button>
                    </div>
                    @elseif($rtImages->count() === 1)
                    <img src="{{ asset('storage/'.$rtImages[0]) }}" class="img-fluid rounded-3 mb-3 w-100"
                         style="height:180px;object-fit:cover" alt="{{ $roomType->name }}">
                    @else
                    <div class="bg-light rounded-3 d-flex align-items-center justify-content-center mb-3"
                         style="height:180px">
                        <i class="bi bi-door-open text-muted" style="font-size:3rem"></i>
                    </div>
                    @endif

                    <h6 class="fw-bold">{{ $roomType->name }}</h6>
                    <div class="d-flex flex-column gap-1 text-muted small">
                        @if($roomType->bed_type)
                        <span><i class="bi bi-moon-stars me-2 text-primary"></i>{{ $roomType->bed_type }}</span>
                        @endif
                        <span><i class="bi bi-people me-2 text-primary"></i>ظرفیت {{ $roomType->capacity }} نفر</span>
                        @if($roomType->size_sqm)
                        <span><i class="bi bi-aspect-ratio me-2 text-primary"></i>{{ $roomType->size_sqm }} متر مربع</span>
                        @endif
                        <span>
                            @if($roomType->has_private_bathroom)
                            <i class="bi bi-check-circle-fill me-2 text-success"></i>حمام اختصاصی
                            @else
                            <i class="bi bi-x-circle me-2 text-muted"></i>بدون حمام اختصاصی
                            @endif
                        </span>
                        @if($roomType->smoking)
                        <span class="text-warning"><i class="bi bi-fire me-2"></i>اتاق سیگاری</span>
                        @else
                        <span class="text-success"><i class="bi bi-slash-circle me-2"></i>غیر سیگاری</span>
                        @endif
                    </div>
                    @if($roomType->amenities && count($roomType->amenities))
                    <div class="mt-2 d-flex flex-wrap gap-1">
                        @foreach($roomType->amenities as $a)
                        <span class="badge bg-light text-dark border" style="font-size:.7rem">{{ $a }}</span>
                        @endforeach
                    </div>
                    @endif
                    @if($roomType->description)
                    <p class="small text-muted mt-2 mb-0">{{ Str::limit($roomType->description, 120) }}</p>
                    @endif
                </div>

                {{-- Right: Rates Table --}}
                <div class="col-md-8 p-0">
                    <table class="table table-bordered mb-0 align-middle h-100">
                        <thead class="table-light">
                            <tr>
                                <th class="text-muted small fw-normal px-3">انتخاب شما</th>
                                <th class="text-muted small fw-normal text-center px-2" style="width:80px">ظرفیت</th>
                                <th class="text-muted small fw-normal text-center px-2" style="width:150px">قیمت امشب</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($roomType->rates as $rate)
                            <tr>
                                <td class="px-3 py-3">
                                    <div class="fw-semibold mb-1">{{ $rate->name }}</div>
                                    @if($rate->breakfast_included)
                                    <div class="text-success small mb-1">
                                        <i class="bi bi-cup-hot-fill me-1"></i>صبحانه رایگان
                                    </div>
                                    @elseif($rate->breakfast_price_per_person)
                                    <div class="text-muted small mb-1">
                                        <i class="bi bi-cup-hot me-1"></i>صبحانه {{ number_format($rate->breakfast_price_per_person) }} تومان/نفر (اختیاری)
                                    </div>
                                    @else
                                    <div class="text-muted small mb-1">
                                        <i class="bi bi-x me-1"></i>بدون صبحانه
                                    </div>
                                    @endif
                                    <div class="small mb-1">
                                        @if($rate->cancellation_policy === 'free')
                                        <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>لغو رایگان</span>
                                        @else
                                        <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i>غیر قابل استرداد</span>
                                        @endif
                                    </div>
                                    <div class="text-muted small">
                                        @if($rate->payment_type === 'pay_at_hotel')
                                        <i class="bi bi-building me-1"></i>پرداخت در محل
                                        @else
                                        <i class="bi bi-credit-card me-1"></i>پرداخت آنلاین
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center px-2">
                                    @for($p = 0; $p < min($roomType->capacity, 5); $p++)
                                    <i class="bi bi-person-fill small text-muted"></i>
                                    @endfor
                                    @if($roomType->capacity > 5)
                                    <span class="text-muted small">+{{ $roomType->capacity - 5 }}</span>
                                    @endif
                                </td>
                                <td class="text-center px-2 py-3">
                                    <div class="fw-bold text-primary" style="font-size:1.1rem">{{ number_format($rate->price_per_night) }}</div>
                                    <div class="text-muted" style="font-size:.72rem">تومان / شب</div>
                                    @auth
                                    <form class="room-reserve-form mt-2"
                                          action="{{ route('bookings.store', $accommodation) }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="room_type_id" value="{{ $roomType->id }}">
                                        <input type="hidden" name="room_rate_id" value="{{ $rate->id }}">
                                        <input type="hidden" name="check_in" class="rt-check-in">
                                        <input type="hidden" name="check_out" class="rt-check-out">
                                        <input type="hidden" name="guests" value="{{ $roomType->capacity }}">
                                        <button type="button" class="btn btn-primary btn-sm w-100"
                                                onclick="reserveRoom(this)">
                                            <i class="bi bi-calendar-check me-1"></i>رزرو
                                        </button>
                                    </form>
                                    @else
                                    <a href="{{ route('auth.mobile') }}" class="btn btn-outline-primary btn-sm w-100 mt-2">
                                        <i class="bi bi-phone me-1"></i>ورود برای رزرو
                                    </a>
                                    @endauth
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
        @endforeach
    </div>
</div>
@endif

{{-- Reviews Section --}}
<div class="container my-5">
    <div class="row">
        <div class="col-lg-8">
            {{-- Average rating --}}
            @if($reviews->count())
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="text-center bg-primary text-white rounded-3 px-4 py-3">
                    <div class="fs-2 fw-bold">{{ number_format($accommodation->averageRating(), 1) }}</div>
                    <small>از ۵</small>
                </div>
                <div>
                    <div class="text-warning fs-5">
                        @for($s = 1; $s <= 5; $s++)
                            <i class="bi bi-star{{ $s <= round($accommodation->averageRating()) ? '-fill' : '' }}"></i>
                        @endfor
                    </div>
                    <div class="text-muted small">{{ $accommodation->reviewCount() }} نظر ثبت‌شده</div>
                </div>
            </div>
            @endif

            <h5 class="fw-bold mb-3"><i class="bi bi-chat-square-text me-2"></i>نظرات مهمانان</h5>

            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if($errors->has('rating'))
                <div class="alert alert-danger">{{ $errors->first('rating') }}</div>
            @endif

            {{-- Review form --}}
            @auth
                @if($canReview && !$userReview)
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="card-title fw-bold mb-3">ثبت نظر شما</h6>
                        <form action="{{ route('reviews.store', $accommodation) }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">امتیاز</label>
                                <div class="d-flex gap-2 fs-4 rating-stars" id="starSelector">
                                    @for($r=1;$r<=5;$r++)
                                    <i class="bi bi-star rating-star text-warning" role="button" data-val="{{ $r }}" style="cursor:pointer"></i>
                                    @endfor
                                </div>
                                <input type="hidden" name="rating" id="ratingInput" value="5">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">نظر شما</label>
                                <textarea name="comment" class="form-control @error('comment') is-invalid @enderror" rows="4" placeholder="تجربه اقامت خود را بنویسید...">{{ old('comment') }}</textarea>
                                @error('comment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-primary px-4">ثبت نظر</button>
                        </form>
                    </div>
                </div>
                @elseif($userReview)
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>شما قبلاً نظر خود را ثبت کرده‌اید.
                </div>
                @endif
            @endauth

            {{-- Reviews list --}}
            @forelse($reviews as $review)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="fw-bold">{{ $review->user->name }}</span>
                            <span class="text-muted small ms-2">{{ $review->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="text-warning">
                            @for($s=1;$s<=5;$s++)
                                <i class="bi bi-star{{ $s <= $review->rating ? '-fill' : '' }} small"></i>
                            @endfor
                        </div>
                    </div>
                    <p class="mb-0 text-muted">{{ $review->comment }}</p>

                    {{-- Host reply --}}
                    @if($review->host_reply)
                    <div class="bg-success bg-opacity-10 border-start border-success border-3 rounded-3 p-3 mt-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-reply-fill text-success small"></i>
                            <span class="fw-semibold small text-success">پاسخ میزبان</span>
                            <span class="text-muted" style="font-size:.72rem">— {{ $review->host_replied_at->diffForHumans() }}</span>
                        </div>
                        <p class="mb-0 small">{{ $review->host_reply }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @empty
            <div class="text-center text-muted py-4">
                <i class="bi bi-chat-square fs-2 d-block mb-2"></i>
                هنوز نظری ثبت نشده است.
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── Reserve room with selected dates ────────────────────────────────────────
function reserveRoom(btn) {
    const ci = document.getElementById('checkIn').value;
    const co = document.getElementById('checkOut').value;
    if (!ci || !co) {
        alert('لطفاً ابتدا تاریخ ورود و خروج را انتخاب کنید.');
        document.getElementById('bookingForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    const form = btn.closest('form');
    form.querySelector('.rt-check-in').value  = ci;
    form.querySelector('.rt-check-out').value = co;
    form.submit();
}

// ── Carousel thumbnail sync ──────────────────────────────────────────────────
var carouselEl = document.getElementById('accCarousel');
if (carouselEl) {
    carouselEl.addEventListener('slide.bs.carousel', function(e) {
        document.querySelectorAll('[data-thumb-idx]').forEach(function(el) {
            el.style.borderColor = parseInt(el.dataset.thumbIdx) === e.to ? '#0d6efd' : '#dee2e6';
        });
    });
}

const pricePerNight = {{ $accommodation->price_per_night }};
const discountPct   = {{ auth()->check() ? auth()->user()->discount_percentage : 0 }};

// Star rating selector
document.querySelectorAll('.rating-star').forEach(star => {
    star.addEventListener('click', function() {
        const val = parseInt(this.dataset.val);
        document.getElementById('ratingInput').value = val;
        document.querySelectorAll('.rating-star').forEach((s, i) => {
            s.className = 'bi bi-star' + (i < val ? '-fill' : '') + ' rating-star text-warning';
        });
    });
    star.addEventListener('mouseenter', function() {
        const val = parseInt(this.dataset.val);
        document.querySelectorAll('.rating-star').forEach((s, i) => {
            s.className = 'bi bi-star' + (i < val ? '-fill' : '') + ' rating-star text-warning';
        });
    });
});
document.getElementById('starSelector')?.addEventListener('mouseleave', function() {
    const val = parseInt(document.getElementById('ratingInput').value) || 5;
    document.querySelectorAll('.rating-star').forEach((s, i) => {
        s.className = 'bi bi-star' + (i < val ? '-fill' : '') + ' rating-star text-warning';
    });
});
// Init stars
(function(){
    const val = parseInt(document.getElementById('ratingInput')?.value) || 5;
    document.querySelectorAll('.rating-star').forEach((s, i) => {
        s.className = 'bi bi-star' + (i < val ? '-fill' : '') + ' rating-star text-warning';
    });
})();

function updatePrice() {
    const ci = $('#checkIn').val();
    const co = $('#checkOut').val();
    if (!ci || !co) { $('#pricePreview').addClass('d-none'); return; }

    const d1 = new Date(ci), d2 = new Date(co);
    const nights = Math.round((d2 - d1) / 86400000);
    if (nights <= 0) return;

    const base     = pricePerNight * nights;
    const discount = Math.round(base * discountPct / 100);
    const total    = base - discount;

    $('#nightsCount').text(nights);
    $('#basePrice').text(base.toLocaleString('fa-IR') + ' تومان');
    $('#discountAmount').text('-' + discount.toLocaleString('fa-IR') + ' تومان');
    $('#totalPrice').text(total.toLocaleString('fa-IR') + ' تومان');
    $('#pricePreview').removeClass('d-none');
}

var ciGreg = $('#checkIn').val();
var coGreg = $('#checkOut').val();
initJalaliRange('#showCalEl', '#checkIn', '#checkOut', '#showDateDisplay', ciGreg, coGreg, updatePrice);
if (ciGreg && coGreg) updatePrice();

@if($accommodation->lat && $accommodation->lng)
const detailMap = L.map('detailMap').setView([{{ $accommodation->lat }}, {{ $accommodation->lng }}], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(detailMap);
L.marker([{{ $accommodation->lat }}, {{ $accommodation->lng }}])
    .bindPopup('{{ $accommodation->name }}').addTo(detailMap).openPopup();
@endif
</script>
@endpush
