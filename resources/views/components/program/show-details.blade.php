@props(['program', 'panel' => 'host'])

@php
    $booking = $program->booking;
    $indexRoute = $panel === 'admin' ? 'admin.programs.index' : 'host.programs.index';
    $bookingShowRoute = $panel === 'admin' ? 'admin.bookings.show' : 'host.bookings.show';
    $paymentDocuments = collect($program->payment_documents ?? [])->filter(fn ($p) => is_string($p) && $p !== '');
    $guestListDocuments = collect($program->guest_list_documents ?? [])->filter(fn ($p) => is_string($p) && $p !== '');
    $beneficiaryDocuments = $program->beneficiaryCosts
        ->filter(fn ($cost) => !empty($cost->documents))
        ->map(fn ($cost) => [
            'label' => $cost->beneficiary?->name ?? 'ذینفع',
            'paths' => collect($cost->documents ?? [])->filter(fn ($p) => is_string($p) && $p !== '')->values()->all(),
        ])
        ->filter(fn ($row) => $row['paths'] !== []);
    $hasDocuments = $paymentDocuments->isNotEmpty()
        || $guestListDocuments->isNotEmpty()
        || $beneficiaryDocuments->isNotEmpty();
@endphp

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a wire:navigate href="{{ route($indexRoute) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i></a>
    <h5 class="fw-bold mb-0"><i class="bi bi-flag-fill me-2 text-success"></i>{{ $program->title }}</h5>
    <span class="badge bg-{{ $program->statusColor() }}">{{ $program->statusLabel() }}</span>
    <span class="badge bg-info-subtle text-info border">{{ $program->programTypeLabel() }}</span>
    <span class="badge bg-secondary-subtle text-secondary border">{{ $program->paymentTypeLabel() }}</span>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold bg-success text-white py-2"><i class="bi bi-info-circle me-1"></i>اطلاعات برنامه</div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6"><span class="text-muted small">اقامتگاه</span><br><strong>{{ $program->accommodation->name }}</strong></div>
                    <div class="col-md-6"><span class="text-muted small">طرف حساب</span><br><strong>{{ $program->counterparty ?: '—' }}</strong></div>
                    @if($booking)
                    <div class="col-md-6"><span class="text-muted small">تاریخ شروع</span><br><strong>@jalali($booking->check_in)</strong></div>
                    <div class="col-md-6"><span class="text-muted small">تاریخ پایان</span><br><strong>@jalali($booking->check_out)</strong></div>
                    @endif
                    <div class="col-md-4"><span class="text-muted small">تعداد نفرات</span><br><strong>{{ number_format($program->guest_count) }}</strong></div>
                    <div class="col-md-4"><span class="text-muted small">اتاق‌های اختصاصی</span><br><strong>{{ $program->rooms_allocated }}</strong></div>
                    @if($program->employer)
                    <div class="col-md-6"><span class="text-muted small">کارفرما</span><br><strong>{{ $program->employer }}</strong></div>
                    @endif
                    @if($program->contractor)
                    <div class="col-md-6"><span class="text-muted small">پیمانکار</span><br><strong>{{ $program->contractor }}</strong></div>
                    @endif
                    @if($program->description)
                    <div class="col-12"><span class="text-muted small">توضیحات</span><br>{{ $program->description }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold bg-warning bg-opacity-75 py-2"><i class="bi bi-cash-stack me-1"></i>اطلاعات مالی (تومان)</div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="text-muted small">قیمت پایه</div><div class="fw-bold">{{ number_format($program->base_price) }}</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="text-muted small">خدمات</div><div class="fw-bold">{{ number_format($program->services_subtotal) }}</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="text-muted small">تخفیف</div><div class="fw-bold text-danger">{{ number_format($program->discount_amount) }}</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2 bg-light"><div class="text-muted small">مبلغ کل</div><div class="fw-bold text-success">{{ number_format($program->total_amount) }}</div></div></div>
                    <div class="col-6 col-md-6"><div class="border rounded p-2"><div class="text-muted small">بیعانه</div><div class="fw-bold text-primary">{{ number_format($program->deposit_amount) }}</div></div></div>
                    <div class="col-6 col-md-6"><div class="border rounded p-2"><div class="text-muted small">باقیمانده</div><div class="fw-bold">{{ number_format($program->remainingAmount()) }}</div></div></div>
                </div>
                @if($program->notes)
                <div class="mt-3 pt-3 border-top">
                    <div class="text-muted small mb-1">یادداشت مالی</div>
                    <div class="small">{{ $program->notes }}</div>
                </div>
                @endif
            </div>
        </div>

        @if($hasDocuments)
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2"><i class="bi bi-folder2-open me-1"></i>مدارک و پیوست‌ها</div>
            <div class="card-body">
                @if($paymentDocuments->isNotEmpty())
                <div class="{{ ($guestListDocuments->isNotEmpty() || $beneficiaryDocuments->isNotEmpty()) ? 'mb-3 pb-3 border-bottom' : '' }}">
                    <div class="small fw-semibold text-muted mb-2">مدارک پرداخت / مالی</div>
                    <x-program.document-list :paths="$paymentDocuments->all()" :compact="true" />
                </div>
                @endif
                @if($guestListDocuments->isNotEmpty())
                <div class="{{ $beneficiaryDocuments->isNotEmpty() ? 'mb-3 pb-3 border-bottom' : '' }}">
                    <div class="small fw-semibold text-muted mb-2">فایل لیست مهمانان (Excel / CSV)</div>
                    <x-program.document-list :paths="$guestListDocuments->all()" :compact="true" />
                </div>
                @endif
                @foreach($beneficiaryDocuments as $docGroup)
                <div class="{{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                    <div class="small fw-semibold text-muted mb-2">مدارک ذینفع: {{ $docGroup['label'] }}</div>
                    <x-program.document-list :paths="$docGroup['paths']" :compact="true" />
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($booking && $booking->bookingRooms->isNotEmpty())
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2"><i class="bi bi-door-open me-1"></i>اتاق‌های فیزیکی</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>#</th><th>نوع</th><th>اتاق</th></tr></thead>
                    <tbody>
                        @foreach($booking->bookingRooms as $i => $line)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $line->roomType?->name ?? '—' }}</td>
                            <td><span class="badge bg-info-subtle text-info border">{{ $line->room?->name ?? '—' }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($booking && $booking->services->isNotEmpty())
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2"><i class="bi bi-bag-plus me-1"></i>خدمات</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>خدمت</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th></tr></thead>
                    <tbody>
                        @foreach($booking->services as $svc)
                        <tr>
                            <td>{{ $svc->name }}</td>
                            <td>{{ $svc->quantity }}</td>
                            <td>{{ number_format($svc->unit_price) }}</td>
                            <td>{{ number_format($svc->total) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($program->beneficiaryCosts->isNotEmpty())
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2"><i class="bi bi-building me-1"></i>ذینفعان</div>
            <div class="card-body">
                @foreach($program->beneficiaryCosts as $cost)
                <div class="border rounded p-2 mb-2">
                    <div class="fw-semibold">{{ $cost->beneficiary?->name }} <span class="text-muted small">({{ $cost->beneficiary?->beneficiary_code }})</span></div>
                    <div class="small">بدهی: <strong>{{ number_format($cost->debt_amount) }} تومان</strong></div>
                    @if($cost->description)<div class="small text-muted">{{ $cost->description }}</div>@endif
                    @if(!empty($cost->documents))
                    <div class="small mt-2">
                        <span class="text-muted">مدارک ضمیمه:</span>
                        <x-program.document-list :paths="$cost->documents" :compact="true" />
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        @if($booking && $booking->guestDetails->isNotEmpty())
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2"><i class="bi bi-person-lines-fill me-1"></i>مهمانان اردو ({{ $booking->guestDetails->count() }} نفر)</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        @php
                            $programHasForeignGuests = $booking->guestDetails->contains(fn ($guest) => $guest->is_foreign_guest);
                        @endphp
                        <tr>
                            <th>#</th>
                            <th>نام</th>
                            <th>{{ $programHasForeignGuests ? 'کد ملی / پاسپورت' : 'کد ملی' }}</th>
                            @if($programHasForeignGuests)
                            <th>محل اقامت</th>
                            @endif
                            <th>موبایل</th>
                            <th>نسبت</th>
                            @if($booking->bookingRooms->count() > 1)
                            <th>اتاق</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($booking->guestDetails->sortBy('sort_order') as $i => $guest)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $guest->full_name }}</td>
                            <td dir="ltr">{{ $guest->identityNumber() ?: '—' }}</td>
                            @if($programHasForeignGuests)
                            <td>{{ $guest->is_foreign_guest ? ($guest->residenceLocationLabel() ?: '—') : '—' }}</td>
                            @endif
                            <td dir="ltr">{{ $guest->mobile ?: '—' }}</td>
                            <td>{{ $guest->relationLabel() ?: '—' }}</td>
                            @if($booking->bookingRooms->count() > 1)
                            <td>
                                @if($guest->bookingRoom?->room)
                                <span class="badge bg-info-subtle text-info border">{{ $guest->bookingRoom->room->name }}</span>
                                @else
                                —
                                @endif
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        @if($booking)
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2"><i class="bi bi-calendar-check me-1"></i>رزرو مرتبط</div>
            <div class="card-body">
                <p class="mb-1 small">کد پیگیری: <strong dir="ltr">{{ $booking->tracking_code }}</strong></p>
                <p class="mb-2 small">وضعیت: <span class="badge bg-secondary">{{ $booking->status }}</span></p>
                <a wire:navigate href="{{ route($bookingShowRoute, $booking) }}" class="btn btn-sm btn-outline-primary w-100">مشاهده رزرو</a>
            </div>
        </div>
        @endif

        @if($panel === 'host')
        <div class="card shadow-sm mb-3">
            <div class="card-body d-grid gap-2">
                <x-host.can page="programs.show" action="delete" :panel="$panel">
                <button wire:click="destroy()" data-swal-confirm="برنامه لغو شود؟" class="btn btn-outline-danger w-100"><i class="bi bi-x-circle me-1"></i>لغو برنامه</button>
                </x-host.can>
            </div>
        </div>
        @endif

        <div class="card shadow-sm">
            <div class="card-body small">
                <div class="text-muted">تاریخ ثبت: @jalali($program->created_at)</div>
                <div class="text-muted mt-1">آخرین ویرایش: @jalali($program->updated_at)</div>
            </div>
        </div>
    </div>
</div>
