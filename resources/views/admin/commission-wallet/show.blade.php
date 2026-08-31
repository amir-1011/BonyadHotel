<div>

@php
    $meta = $entry->meta ?? [];
    $booking = $entry->booking;
    $isAccommodation = $entry->category === \App\Models\PlatformCommissionEntry::CATEGORY_ACCOMMODATION;
@endphp

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm border-{{ $entry->isCredit() ? 'success' : 'danger' }} border-opacity-25">
            <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <span><i class="bi bi-chat-left-text me-2"></i>توضیح کامل این تراکنش</span>
                <span class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-{{ $entry->isCredit() ? 'success' : 'danger' }}-subtle text-{{ $entry->isCredit() ? 'success' : 'danger' }}">
                        {{ $entry->entryTypeLabel() }}
                    </span>
                    <span class="badge bg-secondary-subtle text-secondary">{{ $entry->reasonLabel() }}</span>
                    @if($booking)
                    <a wire:navigate href="{{ route('admin.bookings.show', $booking) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-calendar-check me-1"></i>رزرو {{ $booking->tracking_code }}
                    </a>
                    @endif
                </span>
            </div>
            <div class="card-body">
                <p class="mb-0 lead fs-6" style="line-height:1.9">{{ $entry->fullExplanation() }}</p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-wallet2 me-2"></i>مبلغ این رکورد
            </div>
            <div class="card-body d-flex flex-column justify-content-center text-center">
                <div class="fs-2 fw-bold {{ $entry->commission_amount >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ $entry->commission_amount >= 0 ? '+' : '' }}{{ \App\Support\PdfPersian::toPersianDigits(number_format($entry->commission_amount)) }}
                    <span class="fs-6 fw-normal text-muted">ریال</span>
                </div>
                <div class="text-muted small mt-2">@jalali($entry->created_at) · {{ $entry->created_at->format('H:i') }}</div>
                @if($entry->createdBy)
                <div class="text-muted small">ثبت توسط: {{ $entry->createdBy->name }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- ── محاسبه کارمزد ───────────────────────────────────────── --}}
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-calculator me-2"></i>نحوه محاسبه کارمزد
            </div>
            <ul class="list-group list-group-flush">
                @foreach($entry->commissionCalculationSteps() as $step)
                <li class="list-group-item small">{{ $step }}</li>
                @endforeach
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">دسته</span>
                    <strong>{{ $entry->categoryLabel() }}</strong>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">کلید دسته‌بندی</span>
                    <code class="small">{{ $entry->category_key }}</code>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">نوع رکورد</span>
                    <span>{{ $entry->entryTypeLabel() }} ({{ $entry->reasonLabel() }})</span>
                </li>
            </ul>
        </div>

        @if($entry->reason === 'amount_adjusted')
        <div class="card shadow-sm mt-3 border-warning border-opacity-50">
            <div class="card-header bg-white fw-semibold small text-warning-emphasis">
                <i class="bi bi-arrow-left-right me-2"></i>جزئیات تغییر مبلغ
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">مبلغ تراکنش قبلی</span>
                    <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($meta['previous_transaction_amount'] ?? 0)) }} ریال</span>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">کارمزد خالص قبلی (این دسته)</span>
                    <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($meta['previous_net_commission'] ?? 0)) }} ریال</span>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">مبلغ تراکنش جدید</span>
                    <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($meta['new_transaction_amount'] ?? $entry->transaction_amount)) }} ریال</strong>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">کارمزد هدف جدید</span>
                    <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($meta['new_target_commission'] ?? 0)) }} ریال</strong>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">تفاوت ثبت‌شده در این رکورد</span>
                    <strong class="{{ $entry->commission_amount >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $entry->commission_amount >= 0 ? '+' : '' }}{{ \App\Support\PdfPersian::toPersianDigits(number_format($entry->commission_amount)) }} ریال
                    </strong>
                </li>
            </ul>
        </div>
        @endif

        @if($entry->reason === 'booking_cancelled')
        <div class="card shadow-sm mt-3 border-danger border-opacity-50">
            <div class="card-header bg-white fw-semibold small text-danger">
                <i class="bi bi-x-circle me-2"></i>جزئیات لغو و برگشت
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">کارمزد خالص برگشت‌داده‌شده</span>
                    <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($meta['reversed_net_commission'] ?? abs($entry->commission_amount))) }} ریال</span>
                </li>
                @if(!empty($meta['cancelled_at']))
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">زمان لغو (ثبت سیستم)</span>
                    <span>{{ $meta['cancelled_at'] }}</span>
                </li>
                @endif
                <li class="list-group-item small">
                    <span class="text-muted">توضیح:</span>
                    با لغو رزرو، تمام کارمزد‌های واریزشده برای این بخش به‌طور کامل از کیف پول کسر شد.
                </li>
            </ul>
        </div>
        @endif
    </div>

    {{-- ── اطلاعات رزرو (از meta + booking) ───────────────────────── --}}
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-calendar-check me-2"></i>اطلاعات رزرو مرتبط
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">کد پیگیری</span>
                    <code>{{ $meta['tracking_code'] ?? $booking?->tracking_code ?? '—' }}</code>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">منبع رزرو</span>
                    <span>{{ $entry->bookingSourceLabel() }}</span>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">وضعیت رزرو (هنگام ثبت)</span>
                    <span>{{ match($meta['booking_status'] ?? $booking?->status ?? '') {
                        'confirmed' => 'تأیید شده',
                        'pending'   => 'در انتظار',
                        'cancelled' => 'لغو شده',
                        default     => $meta['booking_status'] ?? '—',
                    } }}</span>
                </li>
                @if($booking)
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">وضعیت فعلی رزرو</span>
                    <span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span>
                </li>
                @endif
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">اقامتگاه</span>
                    <span>{{ $meta['accommodation_name'] ?? $entry->accommodation?->name ?? '—' }}</span>
                </li>
                @if($entry->accommodation?->city)
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">شهر</span>
                    <span>{{ $entry->accommodation->city->name }}</span>
                </li>
                @endif
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">تاریخ ورود / خروج</span>
                    <span>
                        @if(!empty($meta['check_in']))
                            @jalali($meta['check_in'])
                        @elseif($booking)
                            @jalali($booking->check_in)
                        @else — @endif
                        /
                        @if(!empty($meta['check_out']))
                            @jalali($meta['check_out'])
                        @elseif($booking)
                            @jalali($booking->check_out)
                        @else — @endif
                    </span>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">مهمان اصلی</span>
                    <span>{{ $meta['booker_name'] ?? $booking?->bookerName() ?? '—' }}</span>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">موبایل</span>
                    <code>{{ $meta['booker_mobile'] ?? $booking?->bookerMobile() ?? '—' }}</code>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">روش پرداخت</span>
                    <span>{{ $entry->paymentMethodLabel() }}</span>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">مبلغ کل رزرو (هنگام ثبت)</span>
                    <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($meta['total_price'] ?? $booking?->total_price ?? 0)) }} ریال</span>
                </li>
                @if($booking)
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">مبلغ کل فعلی رزرو</span>
                    <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($booking->total_price)) }} ریال</strong>
                </li>
                <li class="list-group-item small d-flex justify-content-between">
                    <span class="text-muted">جمع کارمزد این رزرو (همه بخش‌ها)</span>
                    <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($bookingCommissionNet)) }} ریال</strong>
                </li>
                @endif
            </ul>
        </div>
    </div>
</div>

{{-- ── جزئیات اقامت یا خدمت ─────────────────────────────────────── --}}
<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold small">
        <i class="bi bi-{{ $isAccommodation ? 'house-door' : 'stars' }} me-2"></i>
        جزئیات {{ $isAccommodation ? 'اقامت / رزرو' : 'خدمت' }} — موضوع این کارمزد
    </div>
    <div class="card-body">
        @if($isAccommodation)
        <div class="row g-3">
            <div class="col-md-6">
                <ul class="list-group list-group-flush border rounded">
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">مبلغ تراکنش (بخش اقامت)</span>
                        <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($entry->transaction_amount)) }} ریال</strong>
                    </li>
                    @if(!empty($meta['nights']))
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">تعداد شب</span>
                        <span>{{ $meta['nights'] }} شب</span>
                    </li>
                    @endif
                    @if(!empty($meta['guests']))
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">تعداد مهمان</span>
                        <span>{{ $meta['guests'] }} نفر</span>
                    </li>
                    @endif
                    @if(isset($meta['base_price']))
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">قیمت پایه اقامت (قبل تخفیف)</span>
                        <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($meta['base_price'])) }} ریال</span>
                    </li>
                    @endif
                    @if(isset($meta['discount_amount']) && $meta['discount_amount'] > 0)
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">تخفیف اقامت</span>
                        <span class="text-danger">− {{ \App\Support\PdfPersian::toPersianDigits(number_format($meta['discount_amount'])) }} ریال</span>
                    </li>
                    @endif
                </ul>
            </div>
            @if($booking)
            <div class="col-md-6">
                <ul class="list-group list-group-flush border rounded">
                    @if($booking->roomType)
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">نوع اتاق</span>
                        <span>{{ $booking->roomType->name }}</span>
                    </li>
                    @endif
                    @if($booking->roomRate)
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">تعرفه</span>
                        <span>{{ $booking->roomRate->name }}</span>
                    </li>
                    @endif
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">اتاق مصرف‌شده</span>
                        <span>{{ $booking->rooms_consumed ?? '—' }}</span>
                    </li>
                    @if($booking->extra_guests > 0)
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">نفر اضافه / هزینه</span>
                        <span>{{ $booking->extra_guests }} نفر · {{ \App\Support\PdfPersian::toPersianDigits(number_format($booking->extra_guests_price)) }} ریال</span>
                    </li>
                    @endif
                    @if($booking->veteran_type_applied)
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">گروه ایثارگری</span>
                        <span>{{ $booking->veteranLabelApplied() }}</span>
                    </li>
                    @endif
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">محاسبه roomSubtotal فعلی</span>
                        <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($booking->roomSubtotal() + $booking->extra_guests_price)) }} ریال</span>
                    </li>
                </ul>
            </div>
            @endif
        </div>
        @else
        {{-- خدمت --}}
        <div class="row g-3">
            <div class="col-md-5">
                <ul class="list-group list-group-flush border rounded">
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">نام خدمت</span>
                        <strong>{{ $entry->service_name ?? $meta['description'] ?? '—' }}</strong>
                    </li>
                    @if($entry->serviceCatalog)
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">شناسه کاتالوگ</span>
                        <code>{{ $entry->serviceCatalog->key }}</code>
                    </li>
                    @endif
                    @if(!empty($meta['service_catalog_key']))
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">کلید خدمت</span>
                        <code>{{ $meta['service_catalog_key'] }}</code>
                    </li>
                    @endif
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">مبلغ تراکنش (جمع خدمت)</span>
                        <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($entry->transaction_amount)) }} ریال</strong>
                    </li>
                    @if(isset($meta['quantity']))
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">تعداد کل</span>
                        <span>{{ $meta['quantity'] }}</span>
                    </li>
                    @endif
                    @if(isset($meta['unit_price']))
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">قیمت واحد (میانگین خطوط)</span>
                        <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($meta['unit_price'])) }} ریال</span>
                    </li>
                    @endif
                    @if(!empty($meta['free_units']))
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">جلسات رایگان</span>
                        <span>{{ $meta['free_units'] }}</span>
                    </li>
                    @endif
                    @if(!empty($meta['discount_amount']))
                    <li class="list-group-item small d-flex justify-content-between">
                        <span class="text-muted">تخفیف خدمت</span>
                        <span class="text-danger">− {{ \App\Support\PdfPersian::toPersianDigits(number_format($meta['discount_amount'])) }} ریال</span>
                    </li>
                    @endif
                </ul>
            </div>
            @if(!empty($meta['lines']) && is_array($meta['lines']))
            <div class="col-md-7">
                <div class="small text-muted mb-2">خطوط خدمت در این رزرو (جمع‌شده در یک کارمزد):</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="col-index">ردیف</th>
                                <th>نام</th>
                                <th>قیمت واحد</th>
                                <th>تعداد</th>
                                <th>رایگان</th>
                                <th>تخفیف</th>
                                <th>جمع</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($meta['lines'] as $i => $line)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>{{ $line['name'] ?? '—' }}</td>
                                <td>{{ \App\Support\PdfPersian::toPersianDigits(number_format($line['unit_price'] ?? 0)) }}</td>
                                <td>{{ $line['quantity'] ?? 0 }}</td>
                                <td>{{ $line['free_units'] ?? 0 }}</td>
                                <td>{{ \App\Support\PdfPersian::toPersianDigits(number_format($line['discount_amount'] ?? 0)) }}</td>
                                <td class="fw-semibold">{{ \App\Support\PdfPersian::toPersianDigits(number_format($line['total'] ?? 0)) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="6" class="text-end fw-semibold">جمع مبلغ خدمت (مبنای کارمزد)</td>
                                <td class="fw-bold">{{ \App\Support\PdfPersian::toPersianDigits(number_format($entry->transaction_amount)) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @elseif($booking && $booking->services->isNotEmpty())
            <div class="col-md-7">
                <div class="small text-muted mb-2">خدمات فعلی رزرو (مرجع):</div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr><th>نام</th><th>واحد</th><th>تعداد</th><th>جمع</th></tr>
                        </thead>
                        <tbody>
                            @foreach($booking->services as $svc)
                            <tr>
                                <td>{{ $svc->name }}</td>
                                <td>{{ \App\Support\PdfPersian::toPersianDigits(number_format($svc->unit_price)) }}</td>
                                <td>{{ $svc->quantity }}</td>
                                <td>{{ \App\Support\PdfPersian::toPersianDigits(number_format($svc->total)) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>
</div>

{{-- ── وضعیت فعلی این خط کارمزد ─────────────────────────────────── --}}
@if($entry->booking_id)
<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold small">
        <i class="bi bi-pie-chart me-2"></i>وضعیت فعلی کارمزد این بخش در رزرو
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="border rounded p-3 text-center">
                    <div class="text-muted small">کارمزد خالص این دسته</div>
                    <div class="fs-4 fw-bold {{ $categoryNet >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ \App\Support\PdfPersian::toPersianDigits(number_format($categoryNet)) }} <span class="fs-6 fw-normal">ریال</span>
                    </div>
                </div>
            </div>
            @if($currentTarget)
            <div class="col-md-4">
                <div class="border rounded p-3 text-center">
                    <div class="text-muted small">کارمزد هدف (بر اساس رزرو فعلی)</div>
                    <div class="fs-4 fw-bold text-primary">
                        {{ \App\Support\PdfPersian::toPersianDigits(number_format($currentTarget['commission_amount'])) }} <span class="fs-6 fw-normal">ریال</span>
                    </div>
                    <div class="text-muted small mt-1">مبنای {{ \App\Support\PdfPersian::toPersianDigits(number_format($currentTarget['transaction_amount'])) }} ریال</div>
                </div>
            </div>
            @endif
            <div class="col-md-4">
                <div class="border rounded p-3 text-center">
                    <div class="text-muted small">تعداد رکوردهای این بخش</div>
                    <div class="fs-4 fw-bold">{{ $history->count() }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── تاریخچه کامل این خط کارمزد ────────────────────────────────── --}}
@if($history->isNotEmpty())
<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold small">
        <i class="bi bi-clock-history me-2"></i>تاریخچه کامل تغییرات کارمزد — {{ $entry->categoryLabel() }}
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="col-index">#</th>
                    <th>تاریخ</th>
                    <th>نوع</th>
                    <th>دلیل</th>
                    <th>مبلغ تراکنش</th>
                    <th>کارمزد</th>
                    <th>کارمزد خالص تجمعی</th>
                    <th>توضیح</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @php $runningNet = 0; @endphp
                @foreach($history as $h)
                @php $runningNet += $h->commission_amount; @endphp
                <tr class="{{ $h->id === $entry->id ? 'table-primary' : '' }}">
                    <td class="small text-muted">{{ $h->id }}</td>
                    <td class="small">@jalali($h->created_at)<br><span class="text-muted">{{ $h->created_at->format('H:i:s') }}</span></td>
                    <td>
                        <span class="badge bg-{{ $h->isCredit() ? 'success' : 'danger' }}-subtle text-{{ $h->isCredit() ? 'success' : 'danger' }}">
                            {{ $h->entryTypeLabel() }}
                        </span>
                    </td>
                    <td class="small">{{ $h->reasonLabel() }}</td>
                    <td>{{ \App\Support\PdfPersian::toPersianDigits(number_format($h->transaction_amount)) }}</td>
                    <td class="fw-semibold {{ $h->commission_amount >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $h->commission_amount >= 0 ? '+' : '' }}{{ \App\Support\PdfPersian::toPersianDigits(number_format($h->commission_amount)) }}
                    </td>
                    <td class="fw-semibold">{{ \App\Support\PdfPersian::toPersianDigits(number_format($runningNet)) }}</td>
                    <td class="small" style="max-width:280px">{{ $h->fullExplanation() }}</td>
                    <td>
                        @if($h->id !== $entry->id)
                        <a wire:navigate href="{{ route('admin.commission-wallet.show', $h) }}" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2">جزئیات</a>
                        @else
                        <span class="badge bg-primary">همین صفحه</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── سایر بخش‌های کارمزد همین رزرو ─────────────────────────────── --}}
@if($booking && count($allCategoryTargets) > 1)
<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold small">
        <i class="bi bi-layers me-2"></i>سایر بخش‌های کارمزدی این رزرو
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>بخش</th>
                    <th>مبلغ تراکنش</th>
                    <th>کارمزد هدف</th>
                    <th>کارمزد خالص ثبت‌شده</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allCategoryTargets as $key => $target)
                <tr class="{{ $key === $entry->category_key ? 'table-primary' : '' }}">
                    <td>{{ $key === 'accommodation' ? 'اقامت / رزرو' : ($target['service_name'] ?? $key) }}</td>
                    <td>{{ \App\Support\PdfPersian::toPersianDigits(number_format($target['transaction_amount'])) }}</td>
                    <td>{{ \App\Support\PdfPersian::toPersianDigits(number_format($target['commission_amount'])) }}</td>
                    <td class="fw-semibold">{{ \App\Support\PdfPersian::toPersianDigits(number_format($categoryNets[$key] ?? 0)) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── اطلاعات فنی ────────────────────────────────────────────────── --}}
<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold small" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#techMeta">
        <i class="bi bi-code-slash me-2"></i>اطلاعات فنی و متادیتا
        <i class="bi bi-chevron-down float-end text-muted"></i>
    </div>
    <div class="collapse" id="techMeta">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">شناسه رکورد</span><code>{{ $entry->id }}</code></li>
                        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">شناسه رزرو</span><code>{{ $entry->booking_id ?? '—' }}</code></li>
                        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">شناسه اقامتگاه</span><code>{{ $entry->accommodation_id ?? '—' }}</code></li>
                        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">شناسه کاتالوگ خدمت</span><code>{{ $entry->service_catalog_id ?? '—' }}</code></li>
                        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">ثبت توسط (user_id)</span><code>{{ $entry->created_by ?? '—' }}</code></li>
                        <li class="list-group-item d-flex justify-content-between"><span class="text-muted">created_at</span><code>{{ $entry->created_at->toIso8601String() }}</code></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <pre class="bg-light border rounded p-3 small mb-0" style="max-height:320px;overflow:auto;direction:ltr;text-align:left">{{ json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
