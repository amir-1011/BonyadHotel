@props(['program', 'panel' => 'host'])

@php
    $booking = $program->booking;
    $indexRoute = $panel === 'admin' ? 'admin.programs.index' : 'host.programs.index';
    $bookingShowRoute = $panel === 'admin' ? 'admin.bookings.show' : 'host.bookings.show';
    $supportiveReportRoute = $panel === 'admin' ? 'admin.programs.supportive-report' : 'host.programs.supportive-report';
    $paymentDocuments = collect($program->payment_documents ?? [])->filter(fn ($p) => is_string($p) && $p !== '');
    $guestListDocuments = collect($program->guest_list_documents ?? [])->filter(fn ($p) => is_string($p) && $p !== '');
    $hasDocuments = $paymentDocuments->isNotEmpty() || $guestListDocuments->isNotEmpty();
    $nights = $booking ? max(1, $booking->check_in->diffInDays($booking->check_out)) : null;
    $totalBeneficiaryDebt = (int) $program->beneficiaryCosts->sum('debt_amount');
    $beneficiaryDocumentCount = (int) $program->beneficiaryCosts->sum(
        fn ($cost) => \App\Support\ProgramDocumentPaths::count($cost->documents)
    );
    $pid = $program->id;
    $registeredGuestCount = $booking
        ? $booking->guestDetails->filter(fn ($g) => !\App\Models\BookingGuestDetail::isGenericGuestName($g->full_name, (int) $g->sort_order))->count()
        : 0;
    $city = $program->accommodation->city;
    $province = $city?->province;
@endphp

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a wire:navigate href="{{ route($indexRoute) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i></a>
    <h5 class="fw-bold mb-0"><i class="bi bi-flag-fill me-2 text-success"></i>{{ $program->title }}</h5>
    <span class="badge bg-{{ $program->statusColor() }}">{{ $program->statusLabel() }}</span>
    <span class="badge bg-info-subtle text-info border">{{ $program->programTypeLabel() }}</span>
    <span class="badge bg-secondary-subtle text-secondary border">{{ $program->paymentTypeLabel() }}</span>
    @if($program->payment_type === \App\Models\Program::PAYMENT_SUPPORTIVE)
    <a wire:navigate href="{{ route($supportiveReportRoute, ['year' => $booking?->check_in?->year]) }}" class="badge bg-warning-subtle text-warning border text-decoration-none">
        <i class="bi bi-bar-chart me-1"></i>گزارش حمایتی
    </a>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold bg-success text-white py-2"><i class="bi bi-info-circle me-1"></i>اطلاعات برنامه</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">شناسه برنامه</span><br>
                        <strong dir="ltr">#{{ $program->id }}</strong>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">نوع برنامه</span><br>
                        <strong>{{ $program->programTypeLabel() }}</strong>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">نوع پرداخت</span><br>
                        <strong>{{ $program->paymentTypeLabel() }}</strong>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">اقامتگاه</span><br>
                        <strong>{{ $program->accommodation->name }}</strong>
                        @if($city)
                        <div class="text-muted small mt-1">{{ $city->name }}@if($province) · {{ $province->name }}@endif</div>
                        @endif
                    </div>
                    @if($program->createdBy)
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">ثبت‌کننده</span><br>
                        <strong>{{ $program->createdBy->name }}</strong>
                    </div>
                    @endif
                    @if($booking)
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">تماس مهمان</span><br>
                        <strong>{{ $booking->guest_contact_name ?: '—' }}</strong>
                    </div>
                    @endif
                    @if($booking)
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">تاریخ شروع</span><br>
                        <strong>@jalali($booking->check_in)</strong>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">تاریخ پایان</span><br>
                        <strong>@jalali($booking->check_out)</strong>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">مدت اقامت</span><br>
                        <strong>{{ $nights }} شب</strong>
                    </div>
                    @endif
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">تعداد نفرات (برنامه)</span><br>
                        <strong>{{ number_format($program->guest_count) }}</strong>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">مهمانان ثبت‌شده</span><br>
                        <strong class="{{ $registeredGuestCount < $program->guest_count ? 'text-warning' : 'text-success' }}">
                            {{ $registeredGuestCount }} نفر
                        </strong>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">اتاق‌های اختصاصی</span><br>
                        <strong>{{ $program->rooms_allocated }}</strong>
                    </div>
                    @if($program->contractor)
                    <div class="col-sm-6 col-md-4">
                        <span class="text-muted small">پیمانکار</span><br>
                        <strong>{{ $program->contractor }}</strong>
                    </div>
                    @endif
                    @if($program->description)
                    <div class="col-12">
                        <span class="text-muted small">توضیحات</span>
                        <div class="mt-1">{{ $program->description }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        @if($program->employer || $program->beneficiaryCosts->isNotEmpty())
        <div class="row g-3 mb-3">
            @if($program->employer)
            @php
                $employer = $program->employer;
                $employerSummary = '<strong>' . e($employer->name) . '</strong>';
                if (filled($employer->employer_code)) {
                    $employerSummary .= '<span dir="ltr" class="d-block text-muted">' . e($employer->employer_code) . '</span>';
                }
                if (filled($employer->mobile)) {
                    $employerSummary .= '<span dir="ltr" class="d-block">' . e($employer->mobile) . '</span>';
                }
            @endphp
            <div class="col-sm-6">
                @include('components.booking.show-details.summary-card', [
                    'modalId' => 'pg-modal-employer-' . $pid,
                    'icon' => 'building',
                    'title' => 'کارفرما',
                    'accent' => 'primary',
                    'summary' => $employerSummary,
                ])
            </div>
            @endif

            @if($program->beneficiaryCosts->isNotEmpty())
            @php
                $beneficiaryNames = $program->beneficiaryCosts
                    ->map(fn ($cost) => $cost->beneficiary?->name)
                    ->filter()
                    ->take(2)
                    ->implode('، ');
                $beneficiarySummary = $program->beneficiaryCosts->count() . ' ذینفع';
                if ($totalBeneficiaryDebt > 0) {
                    $beneficiarySummary .= ' · ' . number_format($totalBeneficiaryDebt) . ' تومان بدهی';
                }
                if ($beneficiaryDocumentCount > 0) {
                    $beneficiarySummary .= ' · <span class="badge text-bg-success"><i class="bi bi-paperclip me-1"></i>' . $beneficiaryDocumentCount . ' مدرک</span>';
                }
                if ($beneficiaryNames !== '') {
                    $beneficiarySummary .= '<span class="d-block text-muted mt-1">' . e($beneficiaryNames);
                    if ($program->beneficiaryCosts->count() > 2) {
                        $beneficiarySummary .= ' و ' . ($program->beneficiaryCosts->count() - 2) . ' مورد دیگر';
                    }
                    $beneficiarySummary .= '</span>';
                }
            @endphp
            <div class="col-sm-6">
                @include('components.booking.show-details.summary-card', [
                    'modalId' => 'pg-modal-beneficiaries-' . $pid,
                    'icon' => 'people',
                    'title' => 'ذینفعان',
                    'accent' => 'info',
                    'summary' => $beneficiarySummary,
                ])
            </div>
            @endif
        </div>
        <p class="text-muted small mb-3">
            <i class="bi bi-hand-index me-1"></i>برای مشاهده اطلاعات تکمیلی کارفرما و ذینفعان، روی کارت مربوطه بزنید.
        </p>
        @endif

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold bg-warning bg-opacity-75 py-2"><i class="bi bi-cash-stack me-1"></i>اطلاعات مالی (تومان)</div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="text-muted small">قیمت پایه</div><div class="fw-bold">{{ number_format($program->base_price) }}</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="text-muted small">خدمات</div><div class="fw-bold">{{ number_format($program->services_subtotal) }}</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="text-muted small">تخفیف</div><div class="fw-bold text-danger">{{ number_format($program->discount_amount) }}</div></div></div>
                    <div class="col-6 col-md-3"><div class="border rounded p-2 bg-light"><div class="text-muted small">مبلغ کل</div><div class="fw-bold text-success">{{ number_format($program->total_amount) }}</div></div></div>
                    <div class="col-6 col-md-4"><div class="border rounded p-2"><div class="text-muted small">بیعانه</div><div class="fw-bold text-primary">{{ number_format($program->deposit_amount) }}</div></div></div>
                    <div class="col-6 col-md-4"><div class="border rounded p-2"><div class="text-muted small">باقیمانده</div><div class="fw-bold">{{ number_format($program->remainingAmount()) }}</div></div></div>
                    @if($totalBeneficiaryDebt > 0)
                    <div class="col-6 col-md-4"><div class="border rounded p-2"><div class="text-muted small">جمع بدهی ذینفعان</div><div class="fw-bold text-danger">{{ number_format($totalBeneficiaryDebt) }}</div></div></div>
                    @endif
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
                <div class="{{ $guestListDocuments->isNotEmpty() ? 'mb-3 pb-3 border-bottom' : '' }}">
                    <div class="small fw-semibold text-muted mb-2">مدارک پرداخت / مالی</div>
                    <x-program.document-list :paths="$paymentDocuments->all()" :compact="true" />
                </div>
                @endif
                @if($guestListDocuments->isNotEmpty())
                <div>
                    <div class="small fw-semibold text-muted mb-2">فایل لیست مهمانان (Excel / CSV)</div>
                    <x-program.document-list :paths="$guestListDocuments->all()" :compact="true" />
                    @if($registeredGuestCount === 0)
                    <div class="alert alert-info small py-2 mt-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>فایل پیوست شده؛ مهمانان به‌صورت دستی در سیستم ثبت نشده‌اند.
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @endif

        @if($booking && $booking->bookingRooms->isNotEmpty())
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2"><i class="bi bi-door-open me-1"></i>اتاق‌های فیزیکی ({{ $booking->bookingRooms->count() }})</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>#</th><th>نوع اتاق</th><th>اتاق</th><th>مهمانان</th></tr></thead>
                    <tbody>
                        @foreach($booking->bookingRooms as $i => $line)
                        @php
                            $guestsInRoom = $booking->guestDetails->filter(fn ($g) => (int) $g->booking_room_id === (int) $line->id)->count();
                        @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $line->roomType?->name ?? '—' }}</td>
                            <td><span class="badge bg-info-subtle text-info border">{{ $line->room?->name ?? '—' }}</span></td>
                            <td>{{ $guestsInRoom > 0 ? $guestsInRoom . ' نفر' : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($booking && $booking->services->isNotEmpty())
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2"><i class="bi bi-bag-plus me-1"></i>خدمات ({{ $booking->services->count() }} مورد)</div>
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
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">جمع خدمات</th>
                            <th>{{ number_format($program->services_subtotal) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

        {{ $guestManager ?? '' }}
    </div>

    <div class="col-lg-4">
        @if($booking)
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold py-2"><i class="bi bi-calendar-check me-1"></i>رزرو مرتبط</div>
            <div class="card-body">
                <p class="mb-1 small">کد پیگیری: <strong dir="ltr">{{ $booking->tracking_code }}</strong></p>
                <p class="mb-1 small">وضعیت: <span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span></p>
                @if($booking->guest_contact_name)
                <p class="mb-1 small">نام تماس: <strong>{{ $booking->guest_contact_name }}</strong></p>
                @endif
                @if($nights)
                <p class="mb-2 small text-muted">@jalali($booking->check_in) تا @jalali($booking->check_out) · {{ $nights }} شب</p>
                @endif
                <a wire:navigate href="{{ route($bookingShowRoute, $booking) }}" class="btn btn-sm btn-outline-primary w-100 mb-2">مشاهده رزرو</a>
                @if($registeredGuestCount > 0)
                <a wire:navigate href="{{ route($bookingShowRoute, $booking) }}#guests" class="btn btn-sm btn-outline-secondary w-100">ویرایش مهمانان در رزرو</a>
                @endif
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
                <div class="text-muted">شناسه برنامه: <strong dir="ltr">#{{ $program->id }}</strong></div>
                @if($program->createdBy)
                <div class="text-muted mt-1">ثبت‌کننده: {{ $program->createdBy->name }}</div>
                @endif
                <div class="text-muted mt-1">تاریخ ثبت: @jalali($program->created_at)</div>
                <div class="text-muted mt-1">آخرین ویرایش: @jalali($program->updated_at)</div>
            </div>
        </div>
    </div>
</div>

@if($program->employer)
@include('components.booking.show-details.detail-modal', [
    'id' => 'pg-modal-employer-' . $pid,
    'title' => 'اطلاعات کارفرما',
    'icon' => 'building',
    'size' => 'lg',
    'body' => view('components.program.show-details._modal-employer', compact('program', 'panel'))->render(),
])
@endif

@if($program->beneficiaryCosts->isNotEmpty())
@include('components.booking.show-details.detail-modal', [
    'id' => 'pg-modal-beneficiaries-' . $pid,
    'title' => 'ذینفعان برنامه',
    'icon' => 'people',
    'size' => 'xl',
    'body' => view('components.program.show-details._modal-beneficiaries', compact('program', 'panel'))->render(),
])
@endif
