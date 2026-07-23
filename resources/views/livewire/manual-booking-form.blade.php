<div id="manual-booking-form">
    {{-- Progress --}}
    <div class="mb-4">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
            @foreach([1=>'اتاق و تاریخ', 2=>'مهمان اصلی و ایثارگری', 3=>'پرداخت', 4=>'ذینفعان', 5=>'تأیید'] as $n => $label)
                <button type="button" wire:click="goToStep({{ $n }})"
                        class="btn btn-sm {{ $step === $n ? 'btn-primary' : ($step > $n ? 'btn-outline-success' : 'btn-outline-secondary') }}"
                        @if($n > $step) disabled @endif>
                    <span class="badge bg-{{ $step >= $n ? 'light text-dark' : 'secondary' }} me-1">{{ $n }}</span>{{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    @error('submit')<div class="alert alert-danger">{{ $message }}</div>@enderror

    {{-- Step 1: Room & dates --}}
    @if($step === 1)
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-door-open me-2"></i>انتخاب اتاق و تاریخ</div>
        <div class="card-body">
            <x-booking.widget
                :accommodation="$accommodation"
                :room-types="$roomTypes"
                mode="manual"
                :default-discount-pct="$this->discountPct"
                :prefill-room-type-id="$prefillRoomTypeId"
                :prefill-room-rate-id="$prefillRoomRateId"
                :prefill-room-id="$prefillRoomId"
                :prefill-room-name="$prefillRoomName"
                :prefill-focus-dates="$prefillFocusDates"
            />

            @error('checkIn')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
            @error('checkOut')<div class="text-danger small">{{ $message }}</div>@enderror
            @error('roomLines')<div class="text-danger small">{{ $message }}</div>@enderror

            @if(!empty($roomLines))
            <div class="mt-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold"><i class="bi bi-layers me-1"></i>اتاق‌های انتخاب‌شده ({{ count($roomLines) }})</span>
                    @if($checkIn && $checkOut)
                    <span class="small text-muted" dir="ltr">@jalali($checkIn) → @jalali($checkOut)</span>
                    @endif
                </div>
                @foreach($roomLines as $i => $line)
                @php
                    $rt = $roomTypes->firstWhere('id', $line['room_type_id']);
                    $rate = $rt?->rates->firstWhere('id', $line['room_rate_id'] ?? 0);
                    $lineGuests = (int) $line['adults'] + (int) ($line['children_under_6'] ?? 0) + (int) ($line['extra_guests'] ?? 0);
                @endphp
                <div class="alert alert-light border mb-2 small py-2" wire:key="room-line-{{ $i }}">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <strong>{{ $rt?->name ?? 'اتاق' }}</strong>
                            @if($rate)<span class="text-muted"> — {{ $rate->name }}</span>@endif
                            @if(!empty($line['room_name']))
                            <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle ms-1">{{ $line['room_name'] }}</span>
                            @elseif(!empty($line['room_id']))
                            @php $assignedRoom = $rt?->rooms?->firstWhere('id', $line['room_id']); @endphp
                            @if($assignedRoom)<span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle ms-1">{{ $assignedRoom->name }}</span>@endif
                            @endif
                            <div class="mt-1 text-muted">
                                {{ $line['adults'] }} بزرگسال
                                @if(($line['children_under_6'] ?? 0) > 0) · {{ $line['children_under_6'] }} کودک زیر ۶ سال @endif
                                <span>({{ $lineGuests }} نفر)</span>
                                @if(($line['extra_guests'] ?? 0) > 0) · {{ $line['extra_guests'] }} کف‌خواب @endif
                                @if(!empty($line['bill_full_rooms'])) · رزرو کامل اتاق @elseif($rt && $lineGuests < (int) $rt->capacity) · بدون هزینه تخت خالی @endif
                            </div>
                        </div>
                        <button type="button" wire:click="removeRoomLine({{ $i }})" data-swal-confirm="این اتاق از رزرو حذف شود؟" class="btn btn-sm btn-outline-danger flex-shrink-0" title="حذف">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                @endforeach
                <div class="alert alert-info border-0 py-2 small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    مجموع مهمانان: <strong>{{ $this->totalGuests }} نفر</strong>
                    — برای افزودن اتاق دیگر در همین بازه، نوع اتاق بعدی را انتخاب کنید.
                </div>
            </div>
            @elseif($checkIn && $checkOut)
            <div class="alert alert-light border mt-3 mb-0 small">
                <strong>تاریخ انتخاب‌شده:</strong>
                <span dir="ltr">@jalali($checkIn) → @jalali($checkOut)</span>
                <span class="text-muted d-block mt-1">پس از انتخاب اتاق و تعداد نفرات، «تأیید انتخاب» را بزنید.</span>
            </div>
            @endif

            @if(!empty($pricing))
            <div class="alert alert-light border mt-3 mb-0 small">
                <strong>پیش‌نمایش:</strong>
                {{ $pricing['nights'] }} شب · {{ $pricing['billing_guests'] }} تخت ·
                {{ number_format($pricing['subtotal_before_discount']) }} تومان (قبل از تخفیف)
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Step 2: Booker identity & veteran discount --}}
    @if($step === 2)
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-2"></i>مهمان اصلی و گروه ایثارگری</div>
        <div class="card-body">
            <p class="text-muted small mb-3">ابتدا مشخص کنید مهمان اصلی ایرانی است یا خارجی. برای مهمان ایرانی کد ملی را بررسی کنید؛ برای مهمان خارجی شماره پاسپورت و محل اقامت را وارد کنید.</p>

            <label class="d-flex align-items-center gap-2 border rounded p-3 mb-3 {{ $bookerIsForeignGuest ? 'border-info bg-info-subtle' : '' }}" style="cursor:pointer;">
                <input type="checkbox" wire:model.live="bookerIsForeignGuest" class="form-check-input m-0" @if($bookerVerified) disabled @endif>
                <div>
                    <div class="fw-semibold"><i class="bi bi-globe2 me-1"></i>مهمان خارجی</div>
                    <div class="small text-muted">ثبت با شماره پاسپورت، کشور و شهر اقامت</div>
                </div>
            </label>

            @if($bookerIsForeignGuest)
            {{-- Foreign guest flow --}}
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">شماره پاسپورت</label>
                    <input type="text" wire:model="bookerPassportNumber" class="form-control" placeholder="شماره پاسپورت" dir="ltr" maxlength="32"
                           @if($bookerVerified) readonly @endif>
                    @error('bookerPassportNumber')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label small">نام و نام خانوادگی</label>
                    <input type="text" wire:model="guestContactName" class="form-control" placeholder="نام مهمان اصلی"
                           @if($bookerVerified && $bookerIsExistingUser) readonly @endif>
                    @error('guestContactName')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label small">شماره موبایل</label>
                    <input type="text" wire:model.live="guestContactMobile" class="form-control" placeholder="09xxxxxxxxx" dir="ltr" maxlength="11"
                           @if($bookerVerified && $bookerIsExistingUser) readonly @endif>
                    @error('guestContactMobile')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="row g-2 mb-3">
                @include('components.manual-booking.foreign-guest-location-fields', [
                    'countries' => $countries,
                    'residenceCities' => $residenceCities,
                ])
            </div>

            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <button type="button" wire:click="verifyBooker" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="verifyBooker"
                            @if($bookerVerified) disabled @endif>
                        <span wire:loading.remove wire:target="verifyBooker"><i class="bi bi-search me-1"></i>بررسی</span>
                        <span wire:loading wire:target="verifyBooker">در حال بررسی...</span>
                    </button>
                </div>
                @if($bookerVerified)
                <div class="col-md-3">
                    <button type="button" wire:click="resetBookerVerificationFromUi" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>بررسی مجدد
                    </button>
                </div>
                @endif
            </div>

            @if($bookerVerifyMessage)
            <div class="alert alert-{{ $bookerIsExistingUser ? 'success' : 'info' }} py-2 small mb-3">
                <i class="bi bi-{{ $bookerIsExistingUser ? 'check-circle' : 'info-circle' }} me-1"></i>{{ $bookerVerifyMessage }}
            </div>
            @endif

            @if($bookerVerified)
            <div class="border rounded p-3 bg-light mb-3 small">
                <div class="row g-2">
                    <div class="col-md-3"><span class="text-muted">نام:</span> <strong>{{ $guestContactName ?: '—' }}</strong></div>
                    <div class="col-md-3"><span class="text-muted">موبایل:</span> <strong dir="ltr">{{ $guestContactMobile }}</strong></div>
                    <div class="col-md-3"><span class="text-muted">پاسپورت:</span> <strong dir="ltr">{{ $bookerPassportNumber }}</strong></div>
                    <div class="col-md-3"><span class="text-muted">محل اقامت:</span>
                        <strong>
                            @php
                                $country = $countries->firstWhere('id', $foreignCountryId);
                                $city = $residenceCities->firstWhere('id', $foreignResidenceCityId);
                            @endphp
                            {{ $city?->name ?: '—' }}@if($country)، {{ $country->name }}@endif
                        </strong>
                    </div>
                </div>
            </div>
            @endif
            @else
            {{-- Iranian guest flow --}}
            <p class="text-muted small mb-3">اگر در سیستم باشد، گروه ایثارگری از پروفایل خوانده می‌شود؛ در غیر این صورت نام و موبایل گرفته شده و پس از ثبت رزرو، کاربر جدید ساخته می‌شود.</p>

            {{-- National ID + Verify --}}
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">کد ملی مهمان اصلی</label>
                    <input type="text" wire:model="bookerNationalId" class="form-control" placeholder="کد ملی ۱۰ رقمی" dir="ltr" maxlength="10"
                           @if($bookerVerified) readonly @endif>
                    @error('bookerNationalId')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <button type="button" wire:click="verifyBooker" class="btn btn-primary w-100" wire:loading.attr="disabled" wire:target="verifyBooker"
                            @if($bookerVerified) disabled @endif>
                        <span wire:loading.remove wire:target="verifyBooker"><i class="bi bi-search me-1"></i>بررسی</span>
                        <span wire:loading wire:target="verifyBooker">در حال بررسی...</span>
                    </button>
                </div>
                @if($bookerVerified)
                <div class="col-md-3">
                    <button type="button" wire:click="resetBookerVerificationFromUi" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>بررسی مجدد
                    </button>
                </div>
                @endif
            </div>

            @if($bookerVerifyMessage)
            <div class="alert alert-{{ $bookerIsExistingUser ? 'success' : 'info' }} py-2 small mb-3">
                <i class="bi bi-{{ $bookerIsExistingUser ? 'check-circle' : 'info-circle' }} me-1"></i>{{ $bookerVerifyMessage }}
            </div>
            @endif

            {{-- New user: name + mobile --}}
            @if($bookerVerified && !$bookerIsExistingUser)
            <div class="border rounded p-3 bg-light mb-3">
                <div class="small fw-semibold mb-2"><i class="bi bi-person-plus me-1"></i>اطلاعات مهمان اصلی (کاربر جدید)</div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small">نام و نام خانوادگی</label>
                        <input type="text" wire:model="guestContactName" class="form-control form-control-sm" placeholder="نام مهمان اصلی">
                        @error('guestContactName')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">شماره موبایل</label>
                        <input type="text" wire:model.live="guestContactMobile" class="form-control form-control-sm" placeholder="09xxxxxxxxx" dir="ltr" maxlength="11">
                        @error('guestContactMobile')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            @endif

            {{-- Existing user summary --}}
            @if($bookerVerified && $bookerIsExistingUser)
            <div class="border rounded p-3 bg-light mb-3 small">
                <div class="row g-2">
                    <div class="col-md-4"><span class="text-muted">نام:</span> <strong>{{ $guestContactName ?: '—' }}</strong></div>
                    <div class="col-md-4"><span class="text-muted">موبایل:</span> <strong dir="ltr">{{ $guestContactMobile }}</strong></div>
                    @if($bookerIsForeignGuest)
                    <div class="col-md-4"><span class="text-muted">پاسپورت:</span> <strong dir="ltr">{{ $bookerPassportNumber }}</strong></div>
                    @else
                    <div class="col-md-4"><span class="text-muted">کد ملی:</span> <strong dir="ltr">{{ $bookerNationalId }}</strong></div>
                    @endif
                </div>
            </div>
            @endif

            @endif

            @if($bookerVerified && !$bookerIsForeignGuest)
            <hr>
            <p class="text-muted small mb-2">گروه ایثارگری (حداکثر ۲ گروه — قابل تغییر دستی در صورت نیاز):</p>
            @error('veteranType')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
            @error('selectedVeteranTypes')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
            <div class="row g-2">
                @foreach($veteranGroups as $key => $group)
                @if($key === '') @continue @endif
                <div class="col-md-6">
                    <label class="d-flex align-items-center gap-2 border rounded p-3 {{ in_array((string)$key, $selectedVeteranTypes, true) ? 'border-primary bg-primary-subtle' : '' }}" style="cursor:pointer;">
                        <input
                            type="checkbox"
                            wire:model.live="selectedVeteranTypes"
                            value="{{ $key }}"
                            class="form-check-input m-0"
                            @disabled(count($selectedVeteranTypes) >= 2 && !in_array((string)$key, $selectedVeteranTypes, true))
                        >
                        <div>
                            <div class="fw-semibold">{{ $group['label'] }}</div>
                            <div class="small text-muted">{{ $group['discount'] }}٪ تخفیف اقامت</div>
                        </div>
                    </label>
                </div>
                @endforeach
            </div>
            @if(count($selectedVeteranTypes) > 1)
            <div class="alert alert-info py-2 small mt-2 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                مزایای هر دو گروه با اولویت تخفیف بیشتر محاسبه می‌شود.
                @if(!empty($usageSummary['label']))
                گروه‌های انتخاب‌شده: <strong>{{ $usageSummary['label'] }}</strong>
                @endif
            </div>
            @endif

            @if(!empty($usageSummary))
            @php
                $usedTotal        = (int) ($usageSummary['used_total']             ?? 0);
                $totalQuota       = (int) ($usageSummary['total_quota']            ?? 0);
                $remainingTotal   = (int) ($usageSummary['remaining_total']        ?? 0);
                $usedPeriod       = (int) ($usageSummary['used_in_period']         ?? 0);
                $maxPeriod        = (int) ($usageSummary['max_nights_per_period']  ?? 3);
                $remainPeriod     = (int) ($usageSummary['remaining_period']       ?? 0);
                $periodMonths     = (int) ($usageSummary['period_months']          ?? 6);
                $weeklyUsage      = $usageSummary['weekly_free_usage'] ?? [];
                $hasWeeklyFree    = !empty($weeklyUsage);
                $accDiscount      = (int) ($usageSummary['accommodation_discount'] ?? 0);
                $nightsPerDep     = (int) ($usageSummary['nights_per_dependent']   ?? 6);

                // Progress bar: period usage (more critical cap)
                $periodPct  = $maxPeriod > 0 ? min(100, (int) round($usedPeriod / $maxPeriod * 100)) : 0;
                $totalPct   = $totalQuota > 0 ? min(100, (int) round($usedTotal / $totalQuota * 100)) : 0;

                $periodColor = $periodPct >= 100 ? 'danger' : ($periodPct >= 67 ? 'warning' : 'success');
                $totalColor  = $totalPct  >= 100 ? 'danger' : ($totalPct  >= 67 ? 'warning' : 'primary');

                // How many nights of this booking can receive veteran discount
                $combinedRemain = (int) ($usageSummary['combined_remaining_discounted_nights'] ?? 0);
                $canBookNights = $combinedRemain > 0
                    ? $combinedRemain
                    : min($remainPeriod, $remainingTotal);
                $requestedNights = 0;
                if ($checkIn && $checkOut) {
                    $requestedNights = (int) (new \DateTime($checkIn))->diff(new \DateTime($checkOut))->days;
                }
                $accUsage = $accommodationUsageCheck ?? [];
                if (!empty($accUsage['discounted_nights']) && $requestedNights > 0) {
                    $discountedNights = (int) $accUsage['discounted_nights'];
                } else {
                    $discountedNights = min($canBookNights, $requestedNights);
                }
                $fullRateNights = max(0, $requestedNights - $discountedNights);
                $groupSummaries = $usageSummary['group_summaries'] ?? [];
                $dualGroupCaps = count($groupSummaries) > 1;
                $periodDeductions = $usageSummary['period_deductions'] ?? [];
            @endphp
            <div class="border rounded mt-3" style="font-size:.83rem; overflow:hidden">

                {{-- Header --}}
                <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-light border-bottom flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <i class="bi bi-shield-fill-check text-primary"></i>
                        <span class="fw-semibold">وضعیت سقف استفاده</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary">{{ $usageSummary['label'] }}</span>
                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                            <i class="bi bi-globe2 me-1"></i>سهمیه مشترک بین تمام اقامتگاه‌ها
                        </span>
                    </div>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                        <i class="bi bi-house me-1"></i>
                        @if($dualGroupCaps)
                            تخفیف اقامت: تا {{ $accDiscount }}٪ (چند گروه)
                        @else
                            تخفیف اقامت: {{ $accDiscount }}٪
                        @endif
                    </span>
                </div>

                <div class="px-3 py-2">

                    {{-- ── Period cap (most restrictive) ── --}}
                    @if($dualGroupCaps)
                    <div class="mb-3">
                        <div class="fw-semibold mb-2">
                            <i class="bi bi-calendar-range me-1 text-primary"></i>
                            سقف دوره‌ای هر گروه ({{ $periodMonths }} ماه)
                        </div>
                        @foreach($groupSummaries as $groupSummary)
                        @php
                            $gUsed = (int) ($groupSummary['used_in_period'] ?? 0);
                            $gMax = (int) ($groupSummary['max_nights_per_period'] ?? 3);
                            $gRemain = (int) ($groupSummary['remaining_period'] ?? 0);
                            $gPct = $gMax > 0 ? min(100, (int) round($gUsed / $gMax * 100)) : 0;
                            $gColor = $gPct >= 100 ? 'danger' : ($gPct >= 67 ? 'warning' : 'success');
                        @endphp
                        <div class="mb-2 ps-1">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="small fw-semibold">{{ $groupSummary['label'] ?? '' }}
                                    <span class="text-muted fw-normal">({{ (int) ($groupSummary['accommodation_discount'] ?? 0) }}٪)</span>
                                </span>
                                <span class="text-{{ $gColor }} small fw-bold">{{ $gUsed }} / {{ $gMax }} شب</span>
                            </div>
                            <div class="progress" style="height:6px; border-radius:4px">
                                <div class="progress-bar bg-{{ $gColor }}" style="width:{{ $gPct }}%"></div>
                            </div>
                            <div class="small text-muted mt-1">
                                @if($gRemain > 0)
                                    {{ $gRemain }} شب باقی‌مانده در این دوره
                                @else
                                    <span class="text-danger">سقف این گروه در دوره جاری تکمیل شده</span>
                                @endif
                            </div>
                            @php $gDeductions = $groupSummary['period_deductions'] ?? []; @endphp
                            @if(!empty($gDeductions))
                            <div class="mt-2 ps-2 border-start border-2 border-{{ $gColor }} border-opacity-50">
                                <div class="small fw-semibold text-muted mb-1">
                                    <i class="bi bi-clock-history me-1"></i>رزروهای کاهش‌دهنده سهمیه
                                </div>
                                @foreach($gDeductions as $deduction)
                                <div class="small d-flex justify-content-between gap-2 py-1 {{ !$loop->last ? 'border-bottom border-light' : '' }}">
                                    <span>
                                        <span dir="ltr" class="text-muted">{{ $deduction['tracking_code'] ?? '—' }}</span>
                                        · {{ $deduction['accommodation_name'] }}
                                        · @jalali($deduction['check_in']) تا @jalali($deduction['check_out'])
                                    </span>
                                    <span class="text-{{ $gColor }} fw-bold text-nowrap">{{ $deduction['nights'] }} شب</span>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        @endforeach
                        @if($combinedRemain > 0)
                        <div class="small text-muted">
                            <i class="bi bi-layers me-1"></i>مجموع ظرفیت باقی‌مانده برای تخفیف اقامت: <strong>{{ $combinedRemain }} شب</strong>
                        </div>
                        @endif
                    </div>
                    @else
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="fw-semibold">
                                <i class="bi bi-calendar-range me-1 text-{{ $periodColor }}"></i>
                                سقف دوره‌ای ({{ $periodMonths }} ماه)
                            </span>
                            <span class="text-{{ $periodColor }} fw-bold">
                                {{ $usedPeriod }} / {{ $maxPeriod }} شب استفاده‌شده
                            </span>
                        </div>
                        <div class="progress" style="height:8px; border-radius:4px">
                            <div class="progress-bar bg-{{ $periodColor }}"
                                 style="width:{{ $periodPct }}%"
                                 title="{{ $usedPeriod }} شب از {{ $maxPeriod }} شب"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <span class="text-muted">
                                @if($remainPeriod > 0)
                                    <i class="bi bi-check-circle text-success me-1"></i>{{ $remainPeriod }} شب باقی‌مانده در این دوره
                                @else
                                    <i class="bi bi-x-circle text-danger me-1"></i>سقف این دوره تکمیل شده
                                @endif
                            </span>
                            <span class="text-muted">هر {{ $periodMonths }} ماه تجدید می‌شود</span>
                        </div>
                        @if(!empty($periodDeductions))
                        <div class="mt-2 ps-2 border-start border-2 border-{{ $periodColor }} border-opacity-50">
                            <div class="small fw-semibold text-muted mb-1">
                                <i class="bi bi-clock-history me-1"></i>رزروهای کاهش‌دهنده سهمیه ({{ $periodMonths }} ماه اخیر)
                            </div>
                            @foreach($periodDeductions as $deduction)
                            <div class="small d-flex justify-content-between gap-2 py-1 {{ !$loop->last ? 'border-bottom border-light' : '' }}">
                                <span>
                                    <span dir="ltr" class="text-muted">{{ $deduction['tracking_code'] ?? '—' }}</span>
                                    · {{ $deduction['accommodation_name'] }}
                                    · @jalali($deduction['check_in']) تا @jalali($deduction['check_out'])
                                </span>
                                <span class="text-{{ $periodColor }} fw-bold text-nowrap">{{ $deduction['nights'] }} شب</span>
                            </div>
                            @endforeach
                        </div>
                        @elseif($usedPeriod === 0)
                        <div class="small text-muted mt-2">
                            <i class="bi bi-check-circle text-success me-1"></i>هیچ مصرفی در دوره جاری ثبت نشده
                        </div>
                        @endif
                    </div>
                    @endif

                    {{-- ── Total lifetime quota ── --}}
                    <div class="mb-2 d-none">
                        <div class="d-flex justify-content-between align-items-baseline mb-1">
                            <span class="fw-semibold">
                                <i class="bi bi-infinity me-1 text-{{ $totalColor }}"></i>
                                سقف کل اقامت
                            </span>
                            <span class="text-{{ $totalColor }} fw-bold">
                                {{ $usedTotal }} / {{ $totalQuota }} شب
                            </span>
                        </div>
                        <div class="progress" style="height:6px; border-radius:4px">
                            <div class="progress-bar bg-{{ $totalColor }}"
                                 style="width:{{ $totalPct }}%"></div>
                        </div>
                        <div class="mt-1 text-muted">
                            <i class="bi bi-people me-1"></i>
                            {{ $this->totalGuests }} نفر × {{ $nightsPerDep }} شب/نفر = {{ $totalQuota }} شب کل
                            @if($remainingTotal > 0)
                            · {{ $remainingTotal }} شب باقی‌مانده
                            @else
                            · <span class="text-danger">سقف کل تکمیل شده</span>
                            @endif
                        </div>
                    </div>

                    {{-- ── Veteran discount nights for this booking ── --}}
                    @if($requestedNights > 0)
                        @if($discountedNights > 0 && $fullRateNights > 0)
                        <div class="d-flex align-items-center gap-2 rounded px-2 py-1 mt-2"
                             style="background:#fff8e1; border:1px solid #ffe082">
                            <i class="bi bi-info-circle-fill text-warning"></i>
                            <span>
                                از <strong>{{ $requestedNights }} شب</strong> این رزرو:
                                <strong class="text-success">{{ $discountedNights }} شب</strong> با تخفیف ایثارگری و
                                <strong>{{ $fullRateNights }} شب</strong> با نرخ عادی محاسبه می‌شود
                                @if(!empty($accUsage['group_usage']))
                                <br><span class="text-muted" style="font-size:.78rem">
                                    @foreach($accUsage['group_usage'] as $gKey => $gUnits)
                                        @php $gInfo = $veteranGroups[$gKey] ?? null; @endphp
                                        @if($gInfo)
                                            <span class="d-inline-block me-2">{{ $gUnits }} شب با {{ $gInfo['discount'] }}٪ ({{ $gInfo['label'] }})</span>
                                        @endif
                                    @endforeach
                                </span>
                                @endif
                            </span>
                        </div>
                        @elseif($discountedNights > 0)
                        <div class="d-flex align-items-center gap-2 rounded px-2 py-1 mt-2"
                             style="background:#e8f5e9; border:1px solid #a5d6a7">
                            <i class="bi bi-moon-stars-fill text-success"></i>
                            <span>تمام <strong class="text-success">{{ $discountedNights }} شب</strong> این رزرو با تخفیف ایثارگری محاسبه می‌شود</span>
                        </div>
                        @else
                        <div class="d-flex align-items-center gap-2 rounded px-2 py-1 mt-2"
                             style="background:#fdecea; border:1px solid #ef9a9a">
                            <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                            <span class="text-danger fw-semibold">سقف تخفیف ایثارگری تکمیل شده — تمام {{ $requestedNights }} شب با نرخ عادی محاسبه می‌شود</span>
                        </div>
                        @endif
                    @elseif($canBookNights > 0)
                    <div class="d-flex align-items-center gap-2 rounded px-2 py-1 mt-2"
                         style="background:#e8f5e9; border:1px solid #a5d6a7">
                        <i class="bi bi-moon-stars-fill text-success"></i>
                        <span>حداکثر <strong class="text-success">{{ $canBookNights }} شب</strong> با تخفیف ایثارگری در دسترس است</span>
                    </div>
                    @else
                    <div class="d-flex align-items-center gap-2 rounded px-2 py-1 mt-2"
                         style="background:#fdecea; border:1px solid #ef9a9a">
                        <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                        <span class="text-danger fw-semibold">سقف تخفیف ایثارگری تکمیل شده — شب‌های اضافه با نرخ عادی محاسبه می‌شوند</span>
                    </div>
                    @endif

                    {{-- ── Free sport sessions ── --}}
                    @if($hasWeeklyFree)
                    <div class="border-top mt-3 pt-2">
                        <div class="fw-semibold mb-2">
                            <i class="bi bi-trophy-fill text-warning me-1"></i>جلسات رایگان هفتگی
                            <span class="text-muted fw-normal" style="font-size:.75rem">(هفته ورود: @jalali($checkIn))</span>
                        </div>
                        <div class="row g-2">
                            @foreach([
                                ['key'=>'pool','catalog'=>'pool','icon'=>'droplet-fill','label'=>'استخر'],
                                ['key'=>'gym','catalog'=>'gym','icon'=>'dumbbell','label'=>'بدنسازی'],
                                ['key'=>'multi','catalog'=>'multi_purpose_hall','icon'=>'grid-fill','label'=>'سالن چند منظوره'],
                            ] as $sport)
                            @php
                                $svcUsage = $weeklyUsage[$sport['catalog']] ?? null;
                                if (!$svcUsage) {
                                    continue;
                                }
                                $svcUsed = $svcUsage['used'] ?? 0;
                                $svcQuota = $svcUsage['quota'] ?? 0;
                                $svcRemain = $svcUsage['remaining'] ?? $svcQuota;
                                $svcColor = $svcRemain <= 0 ? 'danger' : ($svcUsed > 0 ? 'warning' : 'success');
                            @endphp
                            <div class="col-4">
                                <div class="text-center border rounded py-2 border-{{ $svcColor }} border-opacity-25" style="background:#fff8e1">
                                    <i class="bi bi-{{ $sport['icon'] }} text-warning d-block mb-1" style="font-size:1.1rem"></i>
                                    <div class="fw-bold text-{{ $svcColor }}" style="font-size:1rem">{{ $svcRemain }}</div>
                                    <div class="text-muted" style="font-size:.72rem">باقی‌مانده از {{ $svcQuota }}</div>
                                    @if($svcUsed > 0)
                                    <div class="text-{{ $svcColor }}" style="font-size:.7rem">{{ $svcUsed }} استفاده‌شده</div>
                                    @endif
                                    <div class="small fw-semibold mt-1">{{ $sport['label'] }}</div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        <div class="text-muted mt-2" style="font-size:.75rem">
                            <i class="bi bi-info-circle me-1"></i>
                            سهمیه رایگان بین تمام رزروهای همان هفته در تمام اقامتگاه‌ها مشترک است — جلسات بیشتر با نرخ عادی محاسبه می‌شود
                        </div>
                    </div>
                    @endif

                    {{-- ── Notes ── --}}
                    @if(!empty($usageSummary['usage_notes']))
                    <div class="border-top mt-2 pt-2 text-muted">
                        <i class="bi bi-journal-text me-1"></i>{{ $usageSummary['usage_notes'] }}
                    </div>
                    @endif

                </div>
            </div>
            @endif
            @endif
        </div>
    </div>
    @endif

    {{-- Step 3: Payment, guests & per-guest services --}}
    @if($step === 3)
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-credit-card me-2"></i>پرداخت و سایر مهمانان</div>
        <div class="card-body">
            {{-- Booker summary from step 2 --}}
            <div class="alert alert-light border mb-4 small">
                <div class="fw-semibold mb-2"><i class="bi bi-person-check me-1"></i>مهمان اصلی @if($bookerIsForeignGuest)<span class="badge bg-info-subtle text-info border border-info-subtle ms-1">خارجی</span>@endif</div>
                <div class="row g-2">
                    <div class="col-md-3"><span class="text-muted">نام:</span> {{ $guestContactName ?: '—' }}</div>
                    <div class="col-md-3"><span class="text-muted">موبایل:</span> <span dir="ltr">{{ $guestContactMobile }}</span></div>
                    @if($bookerIsForeignGuest)
                    <div class="col-md-3"><span class="text-muted">پاسپورت:</span> <span dir="ltr">{{ $bookerPassportNumber }}</span></div>
                    <div class="col-md-3"><span class="text-muted">محل اقامت:</span>
                        @php
                            $summaryCountry = $countries->firstWhere('id', $foreignCountryId);
                            $summaryCity = $residenceCities->firstWhere('id', $foreignResidenceCityId);
                        @endphp
                        {{ $summaryCity?->name ?: '—' }}@if($summaryCountry)، {{ $summaryCountry->name }}@endif
                    </div>
                    @else
                    <div class="col-md-3"><span class="text-muted">کد ملی:</span> <span dir="ltr">{{ $bookerNationalId }}</span></div>
                    <div class="col-md-3"><span class="text-muted">گروه:</span> {{ $veteranType ? ($veteranGroups[$veteranType]['label'] ?? '—') : 'عادی' }}</div>
                    @endif
                </div>
            </div>

            <div class="col-12 mb-4">
                <label class="form-label small">یادداشت</label>
                <textarea wire:model="notes" class="form-control" rows="2"></textarea>
            </div>

            <label class="form-label fw-semibold">روش پرداخت</label>
            <div class="d-flex gap-3 mb-4">
                <label class="border rounded p-3 flex-fill {{ $paymentMethod === 'cash' ? 'border-success bg-success-subtle' : '' }}" style="cursor:pointer;">
                    <input type="radio" wire:model="paymentMethod" value="cash" class="form-check-input me-2"> نقدی
                </label>
                <label class="border rounded p-3 flex-fill {{ $paymentMethod === 'card_terminal' ? 'border-primary bg-primary-subtle' : '' }}" style="cursor:pointer;">
                    <input type="radio" wire:model="paymentMethod" value="card_terminal" class="form-check-input me-2"> کارتخوان
                </label>
            </div>

            @if($veteranType && $this->discountPct > 0)
            @php $excludedCount = $this->nonVeteranDiscountGuestCount(); @endphp
            <div class="alert {{ $excludedCount > 0 ? 'alert-warning border-warning' : 'alert-info' }} small py-2 mb-3">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-{{ $excludedCount > 0 ? 'exclamation-triangle' : 'info-circle' }} mt-1"></i>
                    <div>
                        @if($excludedCount > 0)
                            <strong>{{ $excludedCount }} نفر</strong> با <strong>نرخ عادی</strong> محاسبه می‌شوند
                            و <strong>{{ $this->totalGuests - $excludedCount }} نفر</strong> شامل تخفیف {{ $this->discountPct }}٪ هستند.
                        @else
                            همه مهمانان شامل تخفیف ایثارگری ({{ $this->discountPct }}٪) هستند.
                            برای مهمان غیرتحت‌پوشش، گزینه «نرخ عادی» را فعال کنید.
                        @endif
                    </div>
                </div>
            </div>
            @elseif($veteranType)
            <div class="alert alert-info small py-2 mb-3">
                <i class="bi bi-info-circle me-1"></i>
                برای مهمانانی که عضو خانواده تحت پوشش ایثارگری نیستند، گزینه «نرخ عادی» را فعال کنید.
            </div>
            @endif

            <label class="form-label fw-semibold"><i class="bi bi-people me-1"></i>مهمانان ({{ $this->totalGuests }} نفر)</label>
            @if(count($roomLines) > 1 || collect($guestDetails)->contains(fn ($g) => !empty($g['room_name'])))
            <div class="alert alert-light border small py-2 mb-3">
                <i class="bi bi-info-circle me-1"></i>
                هر مهمان بر اساس اتاق انتخاب‌شده در مرحله قبل، به شماره اتاق خودش اختصاص داده شده است.
            </div>
            @endif
            @php $prevRoomLabel = null; @endphp
            @foreach($guestDetails as $i => $guest)
            @php
                $excluded = !empty($guest['excluded_from_veteran_discount']);
                $canManualDiscount = $this->guestCanReceiveManualDiscount($i);
                $manualPct = (int) ($guest['manual_discount_percentage'] ?? 0);
                $roomLabel = $this->guestRoomLabel($i);
            @endphp
            @if($roomLabel && $roomLabel !== $prevRoomLabel)
            <div class="d-flex align-items-center gap-2 mb-2 mt-1" wire:key="room-heading-{{ $i }}">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                    <i class="bi bi-door-open me-1"></i>اتاق {{ $roomLabel }}
                </span>
            </div>
            @php $prevRoomLabel = $roomLabel; @endphp
            @endif
            <div class="rounded p-3 mb-3 border-2 {{ $excluded ? 'border-warning bg-warning-subtle shadow-sm' : 'border bg-light' }}"
                 wire:key="guest-{{ $i }}"
                 style="border-width:{{ $excluded ? '2px' : '1px' }} !important; transition: background .15s, border-color .15s;">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill {{ $excluded ? 'text-bg-warning' : 'text-bg-secondary' }}">نفر {{ $i + 1 }}</span>
                        @if($i === 0)
                            <span class="small fw-semibold text-muted"><i class="bi bi-person-check me-1"></i>مهمان اصلی</span>
                        @endif
                        @if($veteranType && $this->discountPct > 0)
                            @if($excluded)
                                <span class="badge rounded-pill text-bg-warning">
                                    <i class="bi bi-cash-coin me-1"></i>نرخ عادی
                                </span>
                            @else
                                <span class="badge rounded-pill text-bg-success bg-opacity-75">
                                    <i class="bi bi-tag-fill me-1"></i>تخفیف {{ $this->discountPct }}٪
                                </span>
                            @endif
                        @elseif($canManualDiscount)
                            <span class="badge rounded-pill text-bg-secondary">
                                <i class="bi bi-cash-coin me-1"></i>نرخ عادی
                            </span>
                        @endif
                        @if($manualPct > 0)
                            <span class="badge rounded-pill text-bg-info">
                                <i class="bi bi-percent me-1"></i>تخفیف دستی {{ $manualPct }}٪
                            </span>
                        @endif
                        @if($roomLabel && count($roomLines) <= 1)
                            <span class="badge rounded-pill text-bg-primary bg-opacity-75">
                                <i class="bi bi-door-open me-1"></i>{{ $roomLabel }}
                            </span>
                        @endif
                    </div>
                </div>

                @if($veteranType && $this->discountPct > 0)
                <label class="d-flex align-items-center gap-3 rounded-3 px-3 py-2 mb-3 border user-select-none {{ $excluded ? 'border-warning bg-white' : 'border-success border-opacity-25 bg-success bg-opacity-10' }}"
                       style="cursor:pointer;">
                    <input type="checkbox"
                           class="form-check-input flex-shrink-0 mt-0"
                           style="width:1.15em;height:1.15em;"
                           wire:model.live="guestDetails.{{ $i }}.excluded_from_veteran_discount">
                    <span class="flex-grow-1 small lh-sm">
                        <span class="fw-semibold d-block {{ $excluded ? 'text-warning-emphasis' : 'text-success' }}">
                            {{ $excluded ? 'بدون تخفیف ایثارگری — نرخ عادی' : 'شامل تخفیف ایثارگری' }}
                        </span>
                        <span class="text-muted" style="font-size:.78rem;">
                            {{ $excluded ? 'هزینه اقامت این مهمان با نرخ کامل (بدون تخفیف گروه) محاسبه می‌شود.' : 'این مهمان سهم خود از تخفیف ' . $this->discountPct . '٪ را دریافت می‌کند.' }}
                        </span>
                    </span>
                    <i class="bi bi-{{ $excluded ? 'toggle-on text-warning' : 'toggle-off text-muted' }} fs-4 flex-shrink-0"></i>
                </label>
                @endif

                @if($canManualDiscount)
                <div class="border rounded-3 p-3 mb-3 bg-white">
                    <div class="small fw-semibold mb-2">
                        <i class="bi bi-sliders me-1 text-primary"></i>تخفیف دستی اقامت
                    </div>
                    <p class="text-muted mb-2" style="font-size:.78rem;">
                        برای مهمانانی که شامل تخفیف ایثارگری نیستند، می‌توانید درصد تخفیف اقامت را با ذکر دلیل ثبت کنید.
                    </p>
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small mb-1">درصد تخفیف</label>
                            <div class="input-group input-group-sm">
                                <input type="number"
                                       wire:model.live="guestDetails.{{ $i }}.manual_discount_percentage"
                                       class="form-control"
                                       min="0" max="100" placeholder="۰">
                                <span class="input-group-text">٪</span>
                            </div>
                            @error("guestDetails.{$i}.manual_discount_percentage")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-9">
                            <label class="form-label small mb-1">دلیل تخفیف @if($manualPct > 0)<span class="text-danger">*</span>@endif</label>
                            <input type="text"
                                   wire:model="guestDetails.{{ $i }}.manual_discount_reason"
                                   class="form-control form-control-sm"
                                   placeholder="مثلاً: همکاری قبلی، معرفی مدیر، ...">
                            @error("guestDetails.{$i}.manual_discount_reason")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                @endif

                <div class="row g-2">
                    <div class="col-md-{{ ($i === 0 && $bookerIsForeignGuest) ? '3' : '4' }}">
                        <input type="text" wire:model="guestDetails.{{ $i }}.full_name" class="form-control form-control-sm" placeholder="نام و نام خانوادگی"
                               @if($i === 0 && $bookerVerified) readonly @endif>
                    </div>
                    @if($i === 0 && $bookerIsForeignGuest)
                    <div class="col-md-2">
                        <input type="text" wire:model="guestDetails.{{ $i }}.passport_number" class="form-control form-control-sm" placeholder="شماره پاسپورت" dir="ltr" readonly>
                    </div>
                    <div class="col-md-3">
                        @php
                            $guestResidenceCountry = $countries->firstWhere('id', $foreignCountryId);
                            $guestResidenceCity = $residenceCities->firstWhere('id', $foreignResidenceCityId);
                        @endphp
                        <input type="text" class="form-control form-control-sm" readonly
                               value="{{ $guestResidenceCity?->name ?: '—' }}@if($guestResidenceCountry)، {{ $guestResidenceCountry->name }}@endif"
                               placeholder="محل اقامت">
                    </div>
                    <div class="col-md-2">
                        <input type="text" wire:model="guestDetails.{{ $i }}.mobile" class="form-control form-control-sm" placeholder="موبایل" dir="ltr" readonly>
                    </div>
                    @else
                    <div class="col-md-3">
                        <input type="text" wire:model="guestDetails.{{ $i }}.national_id" class="form-control form-control-sm" placeholder="کد ملی" dir="ltr"
                               @if($i === 0 && $bookerVerified) readonly @endif>
                    </div>
                    <div class="col-md-3">
                        <input type="text" wire:model="guestDetails.{{ $i }}.mobile" class="form-control form-control-sm" placeholder="موبایل" dir="ltr"
                               @if($i === 0 && $bookerVerified) readonly @endif>
                    </div>
                    @endif
                    <div class="col-md-2">
                        @if($i === 0)
                        <select wire:model="guestDetails.{{ $i }}.relation" class="form-select form-select-sm" disabled>
                            <option value="{{ \App\Models\BookingGuestDetail::RELATION_MAIN_GUEST }}">{{ \App\Models\BookingGuestDetail::RELATION_MAIN_GUEST_LABEL }}</option>
                        </select>
                        @else
                        <select wire:model="guestDetails.{{ $i }}.relation" class="form-select form-select-sm">
                            <option value="">— نسبت —</option>
                            @foreach(['همسر', 'پدر', 'مادر', 'فرزند', 'خواهر', 'برادر', 'دوست', 'همکار', 'غیره'] as $rel)
                            <option value="{{ $rel }}">{{ $rel }}</option>
                            @endforeach
                        </select>
                        @endif
                    </div>
                </div>

                {{-- Per-guest services --}}
                <div class="border-top mt-3 pt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-semibold"><i class="bi bi-bag-plus me-1"></i>خدمات این مهمان</span>
                        <button type="button" wire:click="addGuestService({{ $i }})" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus"></i> خدمت
                        </button>
                    </div>

                    @if(!empty($guest['services']))
                    @if($veteranType)
                    <p class="text-muted mb-2" style="font-size:.78rem;">
                        تخفیف و سهمیه رایگان خدمات بر اساس گروه ایثارگری مهمان اصلی محاسبه می‌شود.
                        در صورت نیاز می‌توانید هزینه خدمت را از سهمیه ایثارگری مستثنی کنید.
                    </p>
                    @endif

                    @foreach($guest['services'] ?? [] as $si => $service)
                    @php
                        $catalogId = $service['service_catalog_id'] ?? '';
                        $selectedCatalog = $catalogId && $catalogId !== 'custom'
                            ? $serviceCatalog->firstWhere('id', (int) $catalogId)
                            : null;
                        $activeVariants = $selectedCatalog?->variants?->where('is_active', true) ?? collect();
                        $hasVariants = $activeVariants->isNotEmpty();
                        $catalogMissingVariants = $selectedCatalog && !$hasVariants;
                        $hasVariableDiscount = $selectedCatalog && $selectedCatalog->min_discount !== null && $selectedCatalog->max_discount !== null;
                        $excludedFromQuota = !empty($service['excluded_from_veteran_quota']);
                        $serviceManualPct = (int) ($service['manual_discount_percentage'] ?? 0);
                    @endphp
                    <div class="d-flex align-items-start gap-2 mb-2" wire:key="guest-{{ $i }}-svc-{{ $si }}">
                        <div class="rounded border bg-white p-2 flex-grow-1">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label small mb-1">خدمت</label>
                                <select wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.service_catalog_id"
                                        class="form-select form-select-sm @error('guestDetails.'.$i.'.services.'.$si.'.service_catalog_id') is-invalid @enderror">
                                    <option value="">— انتخاب —</option>
                                    @foreach($serviceCatalog as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                    @endforeach
                                    <option value="custom">سایر (دستی)</option>
                                </select>
                                @error('guestDetails.'.$i.'.services.'.$si.'.service_catalog_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            @if($hasVariants)
                            <div class="col-md-3">
                                <label class="form-label small mb-1">نوع</label>
                                <select wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.service_catalog_variant_id"
                                        class="form-select form-select-sm @error('guestDetails.'.$i.'.services.'.$si.'.service_catalog_variant_id') is-invalid @enderror">
                                    <option value="">— نوع —</option>
                                    @foreach($activeVariants as $variant)
                                    <option value="{{ $variant->id }}">{{ $variant->name }} ({{ number_format($variant->price) }})</option>
                                    @endforeach
                                </select>
                                @error('guestDetails.'.$i.'.services.'.$si.'.service_catalog_variant_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            @elseif($catalogMissingVariants)
                            <div class="col-md-9">
                                <div class="alert alert-warning small py-1 mb-0">نوع و قیمت این خدمت در تنظیمات ایثارگری تعریف نشده.</div>
                            </div>
                            @endif
                            <div class="col-md-{{ $hasVariants ? 3 : 4 }}">
                                <label class="form-label small mb-1">نام</label>
                                <input type="text" wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.name" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">قیمت</label>
                                <x-money-input wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.unit_price" class="form-control form-control-sm" min="0" />
                            </div>
                            <div class="col-md-1">
                                <label class="form-label small mb-1">تعداد</label>
                                <input type="number" wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.quantity" min="1" class="form-control form-control-sm">
                            </div>
                        </div>

                        @if($veteranType && !empty(trim($service['name'] ?? '')))
                        <label class="d-flex align-items-start gap-2 rounded px-2 py-2 mt-2 mb-0 border user-select-none {{ $excludedFromQuota ? 'border-warning bg-warning-subtle' : 'border-secondary border-opacity-25' }}"
                               style="cursor:pointer;">
                            <input type="checkbox"
                                   class="form-check-input flex-shrink-0 mt-1"
                                   wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.excluded_from_veteran_quota">
                            <span class="small lh-sm">
                                <span class="fw-semibold d-block">هزینه این خدمت از سهمیه ایثارگری مهمان اصلی کسر نشود</span>
                                <span class="text-muted" style="font-size:.75rem;">
                                    {{ $excludedFromQuota ? 'این خدمت با نرخ کامل محاسبه می‌شود و سهمیه رایگان/تخفیف ایثارگری مصرف نمی‌کند.' : 'در صورت فعال‌سازی، می‌توانید تخفیف دستی با ذکر دلیل ثبت کنید.' }}
                                </span>
                            </span>
                        </label>
                        @endif

                        @if($excludedFromQuota && !empty(trim($service['name'] ?? '')))
                        <div class="row g-2 mt-2">
                            <div class="col-md-3">
                                <label class="form-label small mb-1">تخفیف دستی ٪</label>
                                <input type="number"
                                       wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.manual_discount_percentage"
                                       class="form-control form-control-sm" min="0" max="100" placeholder="۰">
                                @error("guestDetails.{$i}.services.{$si}.manual_discount_percentage")<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-9">
                                <label class="form-label small mb-1">دلیل تخفیف @if($serviceManualPct > 0)<span class="text-danger">*</span>@endif</label>
                                <input type="text"
                                       wire:model="guestDetails.{{ $i }}.services.{{ $si }}.manual_discount_reason"
                                       class="form-control form-control-sm"
                                       placeholder="مثلاً: پرداخت مستقیم، توافق مدیر، ...">
                                @error("guestDetails.{$i}.services.{$si}.manual_discount_reason")<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        @endif
                        </div>
                        <button type="button"
                                wire:click="removeGuestService({{ $i }}, {{ $si }})"
                                class="btn btn-sm btn-outline-danger flex-shrink-0 mt-4"
                                title="حذف خدمت">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    @endforeach
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Step 4: Beneficiaries --}}
    @if($step === 4)
        @include('livewire.concerns.beneficiary-rows-step', ['beneficiaries' => $beneficiaries])
    @endif

    {{-- Step 5: Success + full booking details --}}
    @if($step === 5)
    <div class="alert alert-success d-flex align-items-center gap-3 mb-3">
        <i class="bi bi-check-circle-fill fs-3"></i>
        <div class="flex-grow-1">
            <div class="fw-semibold">رزرو با موفقیت ثبت شد</div>
            @if($createdBooking)
            <div class="small text-muted">کد پیگیری: <code dir="ltr">{{ $createdBooking->tracking_code }}</code></div>
            @endif
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if($pdfRoute)
            <a href="{{ $pdfRoute }}" target="_blank" class="btn btn-sm btn-success"><i class="bi bi-file-pdf me-1"></i>PDF</a>
            @endif
            @if($bookingShowRoute)
            <a href="{{ $bookingShowRoute }}" wire:navigate class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>صفحه رزرو</a>
            @endif
        </div>
    </div>

    @if($createdBooking)
        @include('components.booking.show-details', ['booking' => $createdBooking, 'panel' => $panel])
    @endif
    @endif

    {{-- Live price breakdown --}}
    @if($step < 5 && !empty($pricing))
    @php
        $accDiscountPct  = $pricing['accommodation_discount_percentage'] ?? $this->discountPct;
        $accSubtotal     = $pricing['room_subtotal'] + ($pricing['extra_guests_total'] ?? 0);
        $accDiscountAmt  = $pricing['veteran_accommodation_discount_amount']
            ?? (int) round($accSubtotal * $accDiscountPct / 100);
        $accDiscountBreakdown = $pricing['accommodation_discount_breakdown'] ?? [];
        $nonDiscountGuests = $pricing['non_veteran_discount_guests'] ?? 0;
        $svcLines        = $pricing['service_lines'] ?? [];
    @endphp
    <div class="card shadow-sm border-primary mb-3">
        <div class="card-header bg-primary bg-opacity-10 py-2 px-3 d-flex align-items-center gap-2">
            <i class="bi bi-receipt text-primary"></i>
            <span class="small fw-semibold text-primary">پیش‌نمایش محاسبه هزینه</span>
        </div>
        <div class="card-body py-2 px-3" style="font-size:.82rem">

            {{-- ── Accommodation ── --}}
            <div class="d-flex justify-content-between py-1">
                <span class="text-muted">
                    اقامت
                    <span class="text-secondary">({{ $pricing['nights'] }} شب
                    @if($pricing['billing_guests'] > 1) · {{ $pricing['billing_guests'] }} تخت @endif)</span>
                </span>
                <span>{{ number_format($pricing['room_subtotal']) }} ت</span>
            </div>

            @if(($pricing['extra_guests_total'] ?? 0) > 0)
            <div class="d-flex justify-content-between py-1">
                <span class="text-muted">کف‌خواب ({{ $this->totalExtraGuests }} نفر)</span>
                <span>{{ number_format($pricing['extra_guests_total']) }} ت</span>
            </div>
            @endif

            @if(($pricing['children_under_6'] ?? 0) > 0 && ($pricing['children_discount_amount'] ?? 0) > 0)
            <div class="d-flex justify-content-between py-1 text-success">
                <span class="text-muted">
                    تخفیف کودک زیر ۶ سال
                    <span class="text-secondary">({{ $pricing['children_under_6'] }} نفر · {{ $pricing['children_under_6_discount_percentage'] ?? $accommodation->childrenUnder6DiscountPercentage() }}٪ نرخ)</span>
                </span>
                <span>− {{ number_format($pricing['children_discount_amount']) }} ت</span>
            </div>
            @endif

            @if($accDiscountAmt > 0)
            <div class="py-1 text-danger">
                <div class="d-flex justify-content-between">
                    <span>
                        <i class="bi bi-tag-fill me-1" style="font-size:.75rem"></i>تخفیف اقامت
                        @if(count($accDiscountBreakdown) <= 1)
                            ({{ $accDiscountBreakdown[0]['discount_percentage'] ?? $accDiscountPct }}٪)
                        @endif
                        @if(($pricing['veteran_discount_nights'] ?? 0) > 0 && ($pricing['veteran_discount_nights'] ?? 0) < ($pricing['nights'] ?? 0))
                        <br><span class="text-muted ms-3" style="font-size:.75rem">فقط {{ $pricing['veteran_discount_nights'] }} شب از {{ $pricing['nights'] }} شب</span>
                        @endif
                        @if($nonDiscountGuests > 0)
                        <br><span class="text-muted ms-3" style="font-size:.75rem">{{ $nonDiscountGuests }} نفر بدون تخفیف ایثارگری</span>
                        @endif
                    </span>
                    @if(empty($accDiscountBreakdown))
                    <span class="fw-semibold">− {{ number_format($pricing['veteran_accommodation_discount_amount'] ?? $accDiscountAmt) }} ت</span>
                    @endif
                </div>
                <x-booking.accommodation-discount-breakdown
                    :breakdown="$accDiscountBreakdown"
                    :total="$pricing['veteran_accommodation_discount_amount'] ?? $accDiscountAmt"
                    compact
                />
            </div>
            @endif

            @if(($pricing['manual_accommodation_discount_amount'] ?? 0) > 0)
            <div class="d-flex justify-content-between py-1 text-danger">
                <span>
                    <i class="bi bi-sliders me-1" style="font-size:.75rem"></i>تخفیف دستی اقامت (مهمانان نرخ عادی)
                </span>
                <span class="fw-semibold">− {{ number_format($pricing['manual_accommodation_discount_amount']) }} ت</span>
            </div>
            @endif

            {{-- ── Services ── --}}
            @if(count($svcLines) > 0)
            <div class="border-top mt-1 pt-1">
                @foreach($svcLines as $line)
                <div class="py-1" wire:key="svc-preview-{{ $loop->index }}">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">
                            {{ $line['name'] }}
                            @if(isset($line['guest_sort_order']))
                            <span class="badge bg-secondary bg-opacity-10 text-secondary ms-1" style="font-size:.68rem">مهمان {{ (int) $line['guest_sort_order'] + 1 }}</span>
                            @endif
                            <span class="text-secondary">({{ $line['quantity'] }} × {{ number_format($line['unit_price']) }})</span>
                        </span>
                        <span>{{ number_format($line['line_subtotal']) }} ت</span>
                    </div>
                    <x-booking.service-discount-breakdown :line="$line" compact />
                </div>
                @endforeach
            </div>
            @endif

            {{-- ── Totals ── --}}
            <div class="border-top mt-2 pt-2">
                <div class="d-flex justify-content-between text-muted py-1">
                    <span>جمع قبل از تخفیف</span>
                    <span>{{ number_format($pricing['subtotal_before_discount']) }} ت</span>
                </div>
                @if(($pricing['discount_amount'] ?? 0) > 0)
                <div class="d-flex justify-content-between text-danger py-1">
                    <span>
                        <i class="bi bi-scissors me-1"></i>مجموع تخفیفات
                        @if($pricing['discount_percentage'] > 0)
                        <span class="badge bg-danger bg-opacity-15 text-danger ms-1" style="font-size:.72rem">{{ $pricing['discount_percentage'] }}٪</span>
                        @endif
                    </span>
                    <span class="fw-semibold">− {{ number_format($pricing['discount_amount']) }} ت</span>
                </div>
                @endif
                <div class="d-flex justify-content-between fw-bold pt-1 mt-1 border-top">
                    <span>مبلغ قابل پرداخت</span>
                    <span class="text-primary fs-6">{{ number_format($pricing['total_price']) }} تومان</span>
                </div>
            </div>

        </div>
    </div>
    @endif

    {{-- Navigation --}}
    <div id="manual-booking-nav" class="d-flex justify-content-between">
        @if($step > 1 && $step < 5)
        <button type="button" wire:click="prevStep" class="btn btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>قبلی</button>
        @else
        <div></div>
        @endif

        @if($step < 4)
        <button type="button" wire:click="nextStep" class="btn btn-primary">بعدی <i class="bi bi-arrow-left ms-1"></i></button>
        @elseif($step === 4)
        <button type="button" wire:click="submit" class="btn btn-success" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="submit"><i class="bi bi-check-circle me-1"></i>ثبت رزرو و صدور فیش</span>
            <span wire:loading wire:target="submit">در حال ثبت...</span>
        </button>
        @endif
    </div>
</div>
