@extends('layouts.app')

@section('title', 'جزئیات رزرو')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow">
            <div class="card-header bg-{{ $booking->statusColor() }} text-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-receipt me-2"></i>جزئیات رزرو
                </h5>
                <span class="badge bg-white text-{{ $booking->statusColor() }} px-3 py-2 fs-6">
                    {{ $booking->statusLabel() }}
                </span>
            </div>
            <div class="card-body p-4">

                {{-- Tracking Code --}}
                <div class="text-center bg-light rounded-3 p-3 mb-4">
                    <div class="small text-muted mb-1">کد رهگیری</div>
                    <div class="tracking-code">{{ $booking->tracking_code }}</div>
                </div>

                {{-- Accommodation Info --}}
                <div class="border rounded-3 p-3 mb-3">
                    <h6 class="fw-bold text-muted mb-2"><i class="bi bi-building me-2"></i>اطلاعات اقامتگاه</h6>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <strong>نام:</strong> {{ $booking->accommodation->name }}
                        </div>
                        <div class="col-md-6">
                            <strong>نوع:</strong> {{ $booking->accommodation->typeLabel() }}
                        </div>
                        <div class="col-12">
                            <strong>موقعیت:</strong>
                            {{ $booking->accommodation->city->province->name }} - {{ $booking->accommodation->city->name }}
                            @if($booking->accommodation->address)
                                - {{ $booking->accommodation->address }}
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Stay Info --}}
                <div class="border rounded-3 p-3 mb-3">
                    <h6 class="fw-bold text-muted mb-2"><i class="bi bi-calendar3 me-2"></i>اطلاعات اقامت</h6>
                    <div class="row g-2">
                        <div class="col-6 col-md-4">
                            <strong>تاریخ ورود:</strong><br>
                            {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_in))->format('Y/m/d') }}
                        </div>
                        <div class="col-6 col-md-4">
                            <strong>تاریخ خروج:</strong><br>
                            {{ \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($booking->check_out))->format('Y/m/d') }}
                        </div>
                        <div class="col-6 col-md-4">
                            <strong>مدت اقامت:</strong><br>
                            {{ $booking->nights }} شب
                        </div>
                        <div class="col-6 col-md-4">
                            <strong>تعداد مهمان:</strong><br>
                            {{ $booking->guests }} نفر
                        </div>
                    </div>
                </div>

                {{-- Room Type / Rate --}}
                @if($booking->roomType)
                <div class="border rounded-3 p-3 mb-4">
                    <h6 class="fw-bold text-muted mb-2"><i class="bi bi-door-open me-2"></i>اتاق رزرو شده</h6>
                    <div class="row g-2 small">
                        <div class="col-6 col-md-4">
                            <strong>نوع اتاق:</strong><br>
                            {{ $booking->roomType->name }}
                        </div>
                        <div class="col-6 col-md-4">
                            <strong>نوع تخت:</strong><br>
                            {{ $booking->roomType->bed_type }}
                        </div>
                        @if($booking->roomRate)
                        <div class="col-6 col-md-4">
                            <strong>تعرفه:</strong><br>
                            {{ $booking->roomRate->name }}
                        </div>
                        <div class="col-6 col-md-4">
                            <strong>صبحانه:</strong><br>
                            @if($booking->roomRate->breakfast_included)
                                <span class="text-success"><i class="bi bi-check-circle-fill"></i> دارد</span>
                            @else
                                <span class="text-muted"><i class="bi bi-x-circle"></i> ندارد</span>
                            @endif
                        </div>
                        <div class="col-6 col-md-4">
                            <strong>سیاست لغو:</strong><br>
                            {{ $booking->roomRate->cancellationLabel() }}
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Price Details --}}
                <div class="border rounded-3 p-3 mb-4">
                    <h6 class="fw-bold text-muted mb-2"><i class="bi bi-cash-coin me-2"></i>جزئیات مالی</h6>
                    <div class="d-flex justify-content-between flex-wrap gap-1 mb-1 small">
                        <span>قیمت پایه ({{ $booking->nights }} شب × {{ number_format($booking->accommodation->price_per_night) }} تومان):</span>
                        <span>{{ number_format($booking->base_price) }} تومان</span>
                    </div>
                    @if($booking->discount_percentage > 0)
                        <div class="d-flex justify-content-between flex-wrap gap-1 mb-1 text-success small">
                            <span>
                                تخفیف {{ $booking->discount_percentage }}٪
                                ({{ $booking->user->veteranLabel() }})
                            </span>
                            <span>- {{ number_format($booking->discount_amount) }} تومان</span>
                        </div>
                    @endif
                    <hr>
                    <div class="d-flex justify-content-between flex-wrap gap-1 fw-bold">
                        <span>مبلغ نهایی:</span>
                        <span class="text-primary">{{ number_format($booking->total_price) }} تومان</span>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('bookings.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-right me-1"></i>بازگشت به لیست رزروها
                    </a>
                    @if($booking->status === 'confirmed' && $booking->check_out >= now()->toDateString())
                        <form action="{{ route('bookings.cancel', $booking) }}" method="POST"
                              onsubmit="return confirm('آیا از لغو این رزرو مطمئن هستید؟')">
                            @csrf
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-x-circle me-1"></i>لغو رزرو
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('accommodations.show', $booking->accommodation) }}" class="btn btn-outline-info btn-sm" target="_blank">
                        <i class="bi bi-building me-1"></i>مشاهده اقامتگاه
                    </a>
                </div>

                {{-- ── Review Section ─────────────────────────────────── --}}
                @if($canReview)
                <div class="mt-4 pt-3 border-top" id="review-section">
                    @if(session('status'))
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i>{{ session('status') }}
                        </div>
                    @endif

                    @if($userReview)
                    {{-- Already reviewed --}}
                    <h6 class="fw-bold mb-3"><i class="bi bi-star-fill text-warning me-2"></i>نظر ثبت‌شده شما</h6>
                    <div class="bg-light rounded-3 p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="text-warning fs-5">
                                @for($s=1;$s<=5;$s++)
                                    <i class="bi bi-star{{ $s <= $userReview->rating ? '-fill' : '' }}"></i>
                                @endfor
                            </div>
                            <span class="text-muted small">{{ $userReview->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="mb-0">{{ $userReview->comment ?? '(بدون متن)' }}</p>
                        @if($userReview->host_reply)
                        <div class="bg-success bg-opacity-10 border-start border-success border-3 rounded-3 p-3 mt-3">
                            <div class="fw-semibold text-success small mb-1"><i class="bi bi-reply-fill me-1"></i>پاسخ میزبان</div>
                            <p class="mb-0 small">{{ $userReview->host_reply }}</p>
                        </div>
                        @endif
                    </div>
                    @else
                    {{-- New review form --}}
                    <h6 class="fw-bold mb-3"><i class="bi bi-star text-warning me-2"></i>نظر خود را ثبت کنید</h6>
                    <p class="text-muted small mb-3">اقامت شما در <strong>{{ $booking->accommodation->name }}</strong> به پایان رسیده. تجربه‌تان را با دیگران به اشتراک بگذارید.</p>
                    @if($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif
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
    </div>
</div>
@endsection
