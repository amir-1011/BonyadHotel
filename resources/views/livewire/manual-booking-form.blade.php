<div id="manual-booking-form">
    <style>
        #manual-booking-form .mbf-pay {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 1.25rem;
        }
        #manual-booking-form .mbf-pay-option {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 0;
            padding: 14px 6px 12px;
            border: 1px solid #e6e5ea;
            border-radius: 12px;
            background: #fff;
            cursor: pointer;
            user-select: none;
            transition: border-color .18s ease, background .18s ease, box-shadow .18s ease;
        }
        #manual-booking-form .mbf-pay-option:hover {
            border-color: #cfcbe8;
            background: #fbfbfe;
        }
        #manual-booking-form .mbf-pay-option.is-active {
            border-color: #7367f0;
            background: #f6f5ff;
            box-shadow: 0 0 0 3px rgba(115, 103, 240, .12);
        }
        #manual-booking-form .mbf-pay-input {
            position: absolute;
            inset: 0;
            opacity: 0;
            margin: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        #manual-booking-form .mbf-pay-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            background: #f3f2f7;
            color: #6e6b7b;
            transition: background .18s ease, color .18s ease, box-shadow .18s ease;
        }
        #manual-booking-form .mbf-pay-option[data-kind="cash"] .mbf-pay-icon { color: #3d8b65; background: #eef7f2; }
        #manual-booking-form .mbf-pay-option[data-kind="card"] .mbf-pay-icon { color: #4d6aa5; background: #eef2f8; }
        #manual-booking-form .mbf-pay-option[data-kind="medical"] .mbf-pay-icon { color: #3d8a96; background: #eef6f7; }
        #manual-booking-form .mbf-pay-option[data-kind="credit"] .mbf-pay-icon { color: #9a7a42; background: #f7f3ea; }
        #manual-booking-form .mbf-pay-option.is-active .mbf-pay-icon {
            background: #7367f0;
            color: #fff;
            box-shadow: 0 6px 14px rgba(115, 103, 240, .28);
        }
        #manual-booking-form .mbf-pay-label {
            font-size: .78rem;
            font-weight: 600;
            color: #6e6b7b;
            line-height: 1.25;
            text-align: center;
        }
        #manual-booking-form .mbf-pay-option.is-active .mbf-pay-label { color: #5e50ee; }
        #manual-booking-form .mbf-pay-option:focus-within { outline: 0; }
        #manual-booking-form .mbf-pay-input:focus-visible ~ .mbf-pay-icon {
            box-shadow: 0 0 0 4px rgba(115, 103, 240, .22);
        }
        @media (max-width: 575.98px) {
            #manual-booking-form .mbf-pay { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        #manual-booking-form .mbf-step-viewport {
            position: relative;
            overflow: visible;
            min-width: 0;
        }
        #manual-booking-form .mbf-step-pane {
            width: 100%;
            min-width: 0;
            backface-visibility: hidden;
        }
        #manual-booking-form .mbf-step-viewport.is-sliding {
            overflow: hidden;
            pointer-events: none;
        }
        #manual-booking-form .mbf-step-pane--ghost {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            margin: 0;
            pointer-events: none;
            z-index: 2;
        }
        #manual-booking-form.mbf-is-sliding .mbf-layout-main {
            overflow-x: clip;
            overflow-y: visible;
        }
        #manual-booking-form.mbf-is-sliding .mbf-layout-aside {
            align-self: flex-start;
        }
        #manual-booking-form.mbf-is-sliding #manual-booking-nav,
        #manual-booking-form.mbf-is-sliding .mbf-stepper-item {
            pointer-events: none;
        }
        @media (prefers-reduced-motion: reduce) {
            #manual-booking-form .mbf-step-pane,
            #manual-booking-form .mbf-step-pane--ghost,
            #manual-booking-form .mbf-step-viewport {
                transition: none !important;
            }
        }
    </style>
    @error('submit')<div class="alert alert-danger">{{ $message }}</div>@enderror

    <div class="row g-3">
        <div class="{{ $step < 5 ? 'col-lg-8' : 'col-12' }} mbf-layout-main">
        <div class="mbf-step-viewport">
        <div class="mbf-step-pane" wire:key="mbf-step-{{ $step }}" data-mbf-step="{{ $step }}">

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
                {{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['subtotal_before_discount'])) }} ریال (قبل از تخفیف)
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
                    : (($usageSummary['unlimited_total_quota'] ?? false)
                        ? $remainPeriod
                        : min($remainPeriod, $remainingTotal));
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
                        @elseif(!empty($usageSummary['group_summaries'][0]['use_tiered_accommodation_discount'] ?? false))
                            تخفیف اقامت: پلکانی
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
                                @if(!empty($accUsage['night_tiers'] ?? $accUsage['night_discounts']))
                                @php
                                    $bookingTierCounts = collect($accUsage['night_tiers'] ?? [])
                                        ->filter(fn ($tier) => \App\Services\AccommodationDiscountTierEngine::tierHasDiscount($tier))
                                        ->countBy(fn ($tier) => \App\Services\AccommodationDiscountTierEngine::tierType($tier)
                                            . '|' . ($tier['pay_amount'] ?? '')
                                            . '|' . ($tier['discount_percentage'] ?? ''));
                                    if ($bookingTierCounts->isEmpty() && empty($accUsage['night_tiers']) && !empty($accUsage['night_discounts'])) {
                                        $bookingTierCounts = collect($accUsage['night_discounts'])
                                            ->filter(fn ($pct) => (int) $pct > 0)
                                            ->countBy()
                                            ->sortKeysDesc();
                                    }
                                @endphp
                                @if($bookingTierCounts->count() > 1)
                                <br><span class="text-muted" style="font-size:.78rem">
                                    پله‌های این رزرو:
                                    @foreach($bookingTierCounts as $tierKey => $count)
                                        @php
                                            [$tierType, $payAmount, $pct] = array_pad(explode('|', (string) $tierKey, 3), 3, '');
                                        @endphp
                                        <span class="d-inline-block me-2">
                                            {{ $count }} شب ×
                                            @if($tierType === 'fixed_pay')
                                                مبلغ ثابت {{ \App\Support\PdfPersian::toPersianDigits(number_format((int) $payAmount)) }} ریال
                                            @elseif($tierType === 'free')
                                                رایگان
                                            @else
                                                {{ is_numeric($tierKey) ? $tierKey : $pct }}٪
                                            @endif
                                        </span>
                                    @endforeach
                                </span>
                                @endif
                                @endif
                                @if(!empty($accUsage['group_usage']))
                                <br><span class="text-muted" style="font-size:.78rem">
                                    @foreach($accUsage['group_usage'] as $gKey => $gUnits)
                                        @php $gInfo = $veteranGroups[$gKey] ?? null; @endphp
                                        @if($gInfo)
                                            <span class="d-inline-block me-2">{{ $gUnits }} شب
                                                @if(!empty($gInfo['use_tiered_accommodation_discount']))
                                                    (پلکانی · {{ $gInfo['label'] }})
                                                @else
                                                    با {{ $gInfo['discount'] }}٪ ({{ $gInfo['label'] }})
                                                @endif
                                            </span>
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
            <div class="card shadow-sm border-primary border-opacity-25 mb-4">
                <div class="card-header bg-primary bg-opacity-10 py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0"
                              style="width:32px;height:32px;font-size:.95rem">
                            <i class="bi bi-person-check"></i>
                        </span>
                        <span class="small fw-semibold text-primary">مهمان اصلی</span>
                    </div>
                    @if($bookerIsForeignGuest)
                    <span class="badge bg-info-subtle text-info border border-info-subtle">خارجی</span>
                    @endif
                </div>
                <div class="card-body py-3 px-3">
                    <div class="row g-2">
                        <div class="col-sm-6 col-md-3">
                            <div class="rounded border bg-light px-3 py-2 h-100">
                                <div class="text-muted small mb-1">نام</div>
                                <div class="fw-semibold">{{ $guestContactName ?: '—' }}</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="rounded border bg-light px-3 py-2 h-100">
                                <div class="text-muted small mb-1">موبایل</div>
                                <div class="fw-semibold" dir="ltr">{{ $guestContactMobile ?: '—' }}</div>
                            </div>
                        </div>
                        @if($bookerIsForeignGuest)
                        <div class="col-sm-6 col-md-3">
                            <div class="rounded border bg-light px-3 py-2 h-100">
                                <div class="text-muted small mb-1">پاسپورت</div>
                                <div class="fw-semibold" dir="ltr">{{ $bookerPassportNumber ?: '—' }}</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="rounded border bg-light px-3 py-2 h-100">
                                <div class="text-muted small mb-1">محل اقامت</div>
                                <div class="fw-semibold">
                                    @php
                                        $summaryCountry = $countries->firstWhere('id', $foreignCountryId);
                                        $summaryCity = $residenceCities->firstWhere('id', $foreignResidenceCityId);
                                    @endphp
                                    {{ $summaryCity?->name ?: '—' }}@if($summaryCountry)، {{ $summaryCountry->name }}@endif
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="col-sm-6 col-md-3">
                            <div class="rounded border bg-light px-3 py-2 h-100">
                                <div class="text-muted small mb-1">کد ملی</div>
                                <div class="fw-semibold" dir="ltr">{{ $bookerNationalId ?: '—' }}</div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="rounded border px-3 py-2 h-100 {{ $this->isRegularRatePayment() ? 'bg-light' : 'bg-success bg-opacity-10 border-success border-opacity-25' }}">
                                <div class="text-muted small mb-1">گروه ایثارگری</div>
                                <div class="fw-semibold {{ $this->isRegularRatePayment() ? '' : 'text-success-emphasis' }}">{{ $this->assignedVeteranGroupLabel() }}</div>
                                @if($this->isRegularRatePayment())
                                <div class="small text-muted mt-1">گروه در پروفایل مهمان ذخیره می‌شود؛ این رزرو با نرخ عادی (بدون تخفیف و بدون کسر سهمیه) محاسبه می‌شود.</div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12 mb-4">
                <label class="form-label small">یادداشت</label>
                <textarea wire:model="notes" class="form-control" rows="2"></textarea>
            </div>

            <label class="form-label fw-semibold">روش پرداخت</label>
            <div class="mbf-pay" role="radiogroup" aria-label="روش پرداخت">
                <label class="mbf-pay-option {{ $paymentMethod === 'card_terminal' ? 'is-active' : '' }}" data-kind="card">
                    <input type="radio" wire:model.live="paymentMethod" value="card_terminal" class="mbf-pay-input">
                    <span class="mbf-pay-icon" aria-hidden="true"><i class="bi bi-credit-card-2-front"></i></span>
                    <span class="mbf-pay-label">کارتخوان</span>
                </label>
                <label class="mbf-pay-option {{ $paymentMethod === 'medical_accommodation' ? 'is-active' : '' }}" data-kind="medical">
                    <input type="radio" wire:model.live="paymentMethod" value="medical_accommodation" class="mbf-pay-input">
                    <span class="mbf-pay-icon" aria-hidden="true"><i class="bi bi-heart-pulse"></i></span>
                    <span class="mbf-pay-label">اسکان درمانی</span>
                </label>
                <label class="mbf-pay-option {{ $paymentMethod === 'credit' ? 'is-active' : '' }}" data-kind="credit">
                    <input type="radio" wire:model.live="paymentMethod" value="credit" class="mbf-pay-input">
                    <span class="mbf-pay-icon" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
                    <span class="mbf-pay-label">اعتباری</span>
                </label>
            </div>

            @if($this->isMedicalAccommodationPayment())
            <div class="alert alert-info border-info mb-4">
                <div class="d-flex align-items-start gap-2 mb-3">
                    <i class="bi bi-hospital mt-1"></i>
                    <div class="small">
                        <div class="fw-semibold mb-1">اسکان درمانی — بدهی بیمه دی، بدون جریمه کنسلی</div>
                        <div>
                            مبلغ اقامت بر اساس نوع تعرفه شبانه محاسبه می‌شود، تخفیف ایثارگری اعمال نمی‌شود
                            و از سهمیه جانبازی کسر نمی‌شود، اما گروه ایثارگری انتخاب‌شده روی پروفایل مهمان ذخیره می‌ماند.
                            مهمان وجه اقامت را پرداخت نمی‌کند و کل مبلغ به‌صورت بدهی کارفرما (بیمه دی) ثبت می‌شود.
                            کاهش تاریخ اقامت بدون جریمه انجام می‌شود. بارگذاری معرفی‌نامه اختیاری است.
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold mb-1">شماره قرارداد <span class="text-danger">*</span></label>
                    <select wire:model.live="medicalContractId" class="form-select @error('medicalContractId') is-invalid @enderror">
                        <option value="">— انتخاب قرارداد —</option>
                        @foreach($medicalContracts as $contract)
                            <option value="{{ $contract->id }}">{{ $contract->displayLabel() }}</option>
                        @endforeach
                    </select>
                    @error('medicalContractId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @if($medicalContracts->isEmpty())
                    <div class="text-danger small mt-1">برای این تاریخ اقامت قرارداد فعالی وجود ندارد. ابتدا از بخش اسکان درمانی قرارداد را ثبت کنید یا بازهٔ قرارداد را بررسی کنید.</div>
                    @endif
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold mb-1">نوع تعرفه <span class="text-danger">*</span></label>
                    <select wire:model.live="medicalTariffId" class="form-select @error('medicalTariffId') is-invalid @enderror" @disabled($medicalContracts->isEmpty())>
                        <option value="">— انتخاب تعرفه —</option>
                        @foreach($medicalTariffs as $tariff)
                            <option value="{{ $tariff->id }}">
                                {{ $tariff->label }}
                                — {{ \App\Support\PdfPersian::toPersianDigits(number_format($tariff->nightly_rate)) }} ریال / شب
                                @if($tariff->max_companions > 0)
                                    · حداکثر {{ $tariff->max_companions }} همراه
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('medicalTariffId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @if($medicalContracts->isNotEmpty() && $medicalTariffs->isEmpty())
                    <div class="text-danger small mt-1">تعرفه فعالی برای قرارداد انتخاب‌شده تعریف نشده است. ابتدا از بخش اسکان درمانی تعرفه را ثبت کنید.</div>
                    @endif
                </div>
                @if(!empty($pricing['medical']))
                @php $med = $pricing['medical']; @endphp
                <div class="small bg-white border rounded p-2 mb-3">
                    <div>تعرفه: <strong>{{ $med['label'] }}</strong></div>
                    @if(!empty($med['contract_number']))
                    <div>شماره قرارداد: <strong dir="ltr">{{ $med['contract_number'] }}</strong></div>
                    @endif
                    <div>بیمار: {{ \App\Support\PdfPersian::toPersianDigits(number_format($med['nightly_rate'])) }} × {{ $med['nights'] }} شب = {{ \App\Support\PdfPersian::toPersianDigits(number_format($med['patient_total'])) }} ریال</div>
                    <div>همراه: {{ $med['companion_count'] }} نفر
                        @if($med['billed_companions'] > 0)
                            ({{ $med['billed_companions'] }} نفر قابل پرداخت × {{ \App\Support\PdfPersian::toPersianDigits(number_format($med['companion_nightly_rate'])) }})
                            = {{ \App\Support\PdfPersian::toPersianDigits(number_format($med['companion_total'])) }} ریال
                        @else
                            (مشمول نرخ / بدون هزینه جداگانه)
                        @endif
                    </div>
                    <div class="fw-semibold mt-1">جمع اقامت درمانی: {{ \App\Support\PdfPersian::toPersianDigits(number_format($med['stay_total'])) }} ریال — بدهی کارفرما</div>
                    <div class="text-muted">قابل پرداخت مهمان: ۰ ریال</div>
                </div>
                @endif
                @if(!empty($pricing['medical_error']))
                <div class="alert alert-danger small py-2">{{ $pricing['medical_error'] }}</div>
                @endif
                <label class="form-label small fw-semibold mb-1">سند معرفی‌نامه <span class="text-muted">(اختیاری)</span></label>
                <input type="file"
                       wire:model="medicalReferralLetter"
                       multiple
                       accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*"
                       class="form-control @error('medicalReferralLetter') is-invalid @enderror @error('medicalReferralLetter.*') is-invalid @enderror">
                <div class="form-text">فرمت مجاز: PDF یا تصویر (JPG, PNG, WEBP) — حداکثر ۱۰ مگابایت — امکان انتخاب چند فایل</div>
                <div wire:loading wire:target="medicalReferralLetter" class="small text-muted mt-1">در حال بارگذاری فایل...</div>
                @error('medicalReferralLetter')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('medicalReferralLetter.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @if($medicalReferralLetter !== [])
                <div class="small text-success mt-1">
                    @foreach($medicalReferralLetter as $letter)
                    <div><i class="bi bi-check-circle me-1"></i>{{ $letter->getClientOriginalName() }}</div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            @if($this->isCreditPayment())
            <div class="alert alert-warning border-warning mb-4">
                <div class="d-flex align-items-start gap-2 mb-3">
                    <i class="bi bi-wallet2 mt-1"></i>
                    <div class="small">
                        <div class="fw-semibold mb-1">پرداخت اعتباری — مهمان عادی بدون تخفیف</div>
                        <div>
                            این رزرو با نرخ کامل ثبت می‌شود، تخفیف ایثارگری و تخفیف دستی اعمال نمی‌شود،
                            و از سهمیه جانبازی مهمان اصلی کسر نخواهد شد.
                            گروه ایثارگری انتخاب‌شده روی پروفایل مهمان ذخیره می‌ماند.
                            بارگذاری سند معرفی‌نامه / مجوز اعتباری اختیاری است.
                        </div>
                    </div>
                </div>
                <label class="form-label small fw-semibold mb-1">سند معرفی‌نامه اعتباری <span class="text-muted">(اختیاری)</span></label>
                <input type="file"
                       wire:model="creditLetter"
                       multiple
                       accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*"
                       class="form-control @error('creditLetter') is-invalid @enderror @error('creditLetter.*') is-invalid @enderror">
                <div class="form-text">فرمت مجاز: PDF یا تصویر (JPG, PNG, WEBP) — حداکثر ۱۰ مگابایت — امکان انتخاب چند فایل</div>
                <div wire:loading wire:target="creditLetter" class="small text-muted mt-1">در حال بارگذاری فایل...</div>
                @error('creditLetter')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @error('creditLetter.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @if($creditLetter !== [])
                <div class="small text-success mt-1">
                    @foreach($creditLetter as $letter)
                    <div><i class="bi bi-check-circle me-1"></i>{{ $letter->getClientOriginalName() }}</div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            @if(!$this->isRegularRatePayment() && $veteranType && $this->discountPct > 0)
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
            @elseif(!$this->isRegularRatePayment() && $veteranType)
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
                        @if($this->isMedicalAccommodationPayment())
                            <span class="badge rounded-pill text-bg-info">
                                <i class="bi bi-hospital me-1"></i>اسکان درمانی
                            </span>
                        @elseif($this->isCreditPayment())
                            <span class="badge rounded-pill text-bg-warning">
                                <i class="bi bi-wallet2 me-1"></i>اعتباری — نرخ عادی
                            </span>
                        @elseif($veteranType && $this->discountPct > 0)
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

                @if(!$this->isRegularRatePayment() && $veteranType && $this->discountPct > 0)
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
                @php
                    $otherManualDiscountIndices = ($i === 0 && count($guestDetails) > 1)
                        ? collect($guestDetails)->keys()->filter(fn ($idx) => $idx !== 0 && $this->guestCanReceiveManualDiscount($idx))->values()->all()
                        : [];
                @endphp
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
                                   wire:model.live="guestDetails.{{ $i }}.manual_discount_reason"
                                   class="form-control form-control-sm"
                                   placeholder="مثلاً: همکاری قبلی، معرفی مدیر، ...">
                            @error("guestDetails.{$i}.manual_discount_reason")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    @if($i === 0 && count($otherManualDiscountIndices) > 0)
                    <div class="mt-2 pt-2 border-top">
                        <button type="button"
                                wire:click="applyMainManualDiscountToAllGuests"
                                wire:loading.attr="disabled"
                                wire:target="applyMainManualDiscountToAllGuests"
                                class="btn btn-sm btn-outline-primary">
                            <span wire:loading.remove wire:target="applyMainManualDiscountToAllGuests">
                                <i class="bi bi-copy me-1"></i>اعمال همین تخفیف و دلیل برای همه مهمانان
                            </span>
                            <span wire:loading wire:target="applyMainManualDiscountToAllGuests">در حال اعمال...</span>
                        </button>
                    </div>
                    @endif
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
                    @php
                        $hasPendingService = collect($guest['services'] ?? [])->contains(fn ($svc) => empty($svc['confirmed']));
                    @endphp
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-semibold"><i class="bi bi-bag-plus me-1"></i>خدمات این مهمان</span>
                        <button type="button"
                                wire:click="addGuestService({{ $i }})"
                                class="btn btn-sm btn-outline-primary"
                                @disabled($hasPendingService)>
                            <i class="bi bi-plus"></i> خدمت
                        </button>
                    </div>
                    @error("guestServices.{$i}")<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                    @if(!empty($guest['services']))
                    @if(!$this->isRegularRatePayment() && $veteranType)
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
                        $excludedFromQuota = !empty($service['excluded_from_veteran_quota']);
                        $serviceManualPct = (int) ($service['manual_discount_percentage'] ?? 0);
                        $isConfirmed = !empty($service['confirmed']);
                        $serviceSubtotal = (int) ($service['unit_price'] ?? 0) * max(1, (int) ($service['quantity'] ?? 1));
                    @endphp

                    @if($isConfirmed)
                    <div class="border rounded-3 p-3 mb-3 bg-light border-success border-opacity-25" wire:key="guest-{{ $i }}-svc-{{ $si }}-confirmed">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="flex-grow-1">
                                <div class="fw-semibold text-success-emphasis">
                                    <i class="bi bi-check-circle-fill me-1"></i>{{ $service['name'] }}
                                </div>
                                <div class="small text-muted mt-1">
                                    {{ \App\Support\PdfPersian::toPersianDigits(number_format((int) ($service['unit_price'] ?? 0))) }} ریال
                                    <span class="mx-1">×</span>
                                    {{ (int) ($service['quantity'] ?? 1) }}
                                    <span class="mx-1">=</span>
                                    <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($serviceSubtotal)) }} ریال</strong>
                                </div>
                                @if(!$this->isRegularRatePayment() && $excludedFromQuota)
                                <span class="badge text-bg-warning mt-1">خارج از سهمیه ایثارگری</span>
                                @endif
                            </div>
                            <button type="button"
                                    wire:click="removeGuestService({{ $i }}, {{ $si }})"
                                    class="btn btn-sm btn-outline-danger flex-shrink-0"
                                    title="حذف خدمت">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    @else
                    <div class="border rounded-3 p-3 mb-3 bg-white shadow-sm border-primary border-opacity-25" wire:key="guest-{{ $i }}-svc-{{ $si }}-draft">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="small fw-semibold text-primary">
                                <i class="bi bi-pencil-square me-1"></i>افزودن خدمت
                            </span>
                            <span class="badge text-bg-warning">در انتظار تأیید</span>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
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
                            <div class="col-12">
                                <label class="form-label small mb-1">نوع</label>
                                <select wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.service_catalog_variant_id"
                                        class="form-select form-select-sm @error('guestDetails.'.$i.'.services.'.$si.'.service_catalog_variant_id') is-invalid @enderror">
                                    <option value="">— نوع —</option>
                                    @foreach($activeVariants as $variant)
                                    <option value="{{ $variant->id }}">{{ $variant->name }} ({{ \App\Support\PdfPersian::toPersianDigits(number_format($variant->price)) }})</option>
                                    @endforeach
                                </select>
                                @error('guestDetails.'.$i.'.services.'.$si.'.service_catalog_variant_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            @elseif($catalogMissingVariants)
                            <div class="col-12">
                                <div class="alert alert-warning small py-2 mb-0">نوع و قیمت این خدمت در تنظیمات ایثارگری تعریف نشده.</div>
                            </div>
                            @endif

                            <div class="col-12">
                                <label class="form-label small mb-1">نام</label>
                                <input type="text" wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.name" class="form-control form-control-sm @error('guestDetails.'.$i.'.services.'.$si.'.name') is-invalid @enderror">
                                @error('guestDetails.'.$i.'.services.'.$si.'.name')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label small mb-1">قیمت (ریال)</label>
                                <x-money-input wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.unit_price" class="form-control form-control-sm" min="0" />
                                @error('guestDetails.'.$i.'.services.'.$si.'.unit_price')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small mb-1">تعداد</label>
                                <input type="number" wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.quantity" min="1" class="form-control form-control-sm @error('guestDetails.'.$i.'.services.'.$si.'.quantity') is-invalid @enderror">
                                @error('guestDetails.'.$i.'.services.'.$si.'.quantity')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        @if(!$this->isRegularRatePayment() && $veteranType && !empty(trim($service['name'] ?? '')))
                        <label class="d-flex align-items-start gap-2 rounded px-2 py-2 mt-3 mb-0 border user-select-none {{ $excludedFromQuota ? 'border-warning bg-warning-subtle' : 'border-secondary border-opacity-25' }}"
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

                        @if(!$this->isRegularRatePayment() && $excludedFromQuota && !empty(trim($service['name'] ?? '')))
                        <div class="row g-2 mt-3">
                            <div class="col-sm-4">
                                <label class="form-label small mb-1">تخفیف دستی ٪</label>
                                <input type="number"
                                       wire:model.live="guestDetails.{{ $i }}.services.{{ $si }}.manual_discount_percentage"
                                       class="form-control form-control-sm" min="0" max="100" placeholder="۰">
                                @error("guestDetails.{$i}.services.{$si}.manual_discount_percentage")<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-8">
                                <label class="form-label small mb-1">دلیل تخفیف @if($serviceManualPct > 0)<span class="text-danger">*</span>@endif</label>
                                <input type="text"
                                       wire:model="guestDetails.{{ $i }}.services.{{ $si }}.manual_discount_reason"
                                       class="form-control form-control-sm"
                                       placeholder="مثلاً: پرداخت مستقیم، توافق مدیر، ...">
                                @error("guestDetails.{$i}.services.{$si}.manual_discount_reason")<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        @endif

                        <div class="d-flex gap-2 justify-content-end mt-3 pt-3 border-top">
                            <button type="button"
                                    wire:click="removeGuestService({{ $i }}, {{ $si }})"
                                    class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x-lg me-1"></i>انصراف
                            </button>
                            <button type="button"
                                    wire:click="confirmGuestService({{ $i }}, {{ $si }})"
                                    class="btn btn-sm btn-primary"
                                    wire:loading.attr="disabled"
                                    wire:target="confirmGuestService({{ $i }}, {{ $si }})">
                                <span wire:loading.remove wire:target="confirmGuestService({{ $i }}, {{ $si }})">
                                    <i class="bi bi-check2 me-1"></i>تأیید خدمت
                                </span>
                                <span wire:loading wire:target="confirmGuestService({{ $i }}, {{ $si }})">در حال بررسی...</span>
                            </button>
                        </div>
                    </div>
                    @endif
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
        @include('livewire.concerns.beneficiary-rows-step', ['beneficiaries' => $beneficiaries, 'provinces' => $provinces])
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
        @include('components.booking.show-details', [
            'booking' => $createdBooking,
            'panel' => $panel,
            'canModifyBookingRooms' => false,
            'canEditGuestNames' => false,
            'canExtendStay' => false,
        ])
    @endif
    @endif

        </div>{{-- /.mbf-step-pane --}}
        </div>{{-- /.mbf-step-viewport --}}
        </div>{{-- /.mbf-layout-main --}}

        @if($step < 5)
        <div class="col-lg-4 mbf-layout-aside order-first order-lg-0">
            <div class="mbf-aside-sticky">
    @php
        $mbfSteps = [
            1 => ['label' => 'اتاق و تاریخ', 'icon' => 'bi-calendar3', 'num' => '۱'],
            2 => ['label' => 'مهمان اصلی و ایثارگری', 'icon' => 'bi-person-badge', 'num' => '۲'],
            3 => ['label' => 'پرداخت', 'icon' => 'bi-credit-card', 'num' => '۳'],
            4 => ['label' => 'ذینفعان', 'icon' => 'bi-people', 'num' => '۴'],
            5 => ['label' => 'تأیید', 'icon' => 'bi-clipboard-check', 'num' => '۵'],
        ];
    @endphp
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3 px-3">
            <nav class="mbf-stepper" aria-label="مراحل رزرو">
                @foreach($mbfSteps as $n => $meta)
                    @php
                        $state = $step === $n ? 'is-active' : ($step > $n ? 'is-done' : 'is-pending');
                    @endphp
                    <button type="button"
                            wire:click="goToStep({{ $n }})"
                            data-mbf-slide="{{ $n < $step ? -1 : 1 }}"
                            class="mbf-stepper-item {{ $state }}"
                            @if($n > $step) disabled @endif>
                        <span class="mbf-stepper-track">
                            <span class="mbf-stepper-icon">
                                @if($step > $n)
                                    <i class="bi bi-check-lg"></i>
                                @else
                                    <i class="bi {{ $meta['icon'] }}"></i>
                                @endif
                            </span>
                        </span>
                        <span class="mbf-stepper-body">
                            <span class="mbf-stepper-kicker">مرحله {{ $meta['num'] }}</span>
                            <span class="mbf-stepper-title">{{ $meta['label'] }}</span>
                        </span>
                    </button>
                @endforeach
            </nav>
        </div>
    </div>

    {{-- Live price breakdown --}}
    @if(!empty($pricing))
    @php
        $accDiscountPct  = $pricing['accommodation_discount_percentage'] ?? $this->discountPct;
        $accSubtotal     = $pricing['room_subtotal'] + ($pricing['extra_guests_total'] ?? 0);
        $accDiscountAmt  = $pricing['veteran_accommodation_discount_amount']
            ?? (int) round($accSubtotal * $accDiscountPct / 100);
        $accDiscountBreakdown = $pricing['accommodation_discount_breakdown'] ?? [];
        $nonDiscountGuests = $pricing['non_veteran_discount_guests'] ?? 0;
        $svcLines        = $pricing['service_lines'] ?? [];
        $isTieredAcc     = (bool) ($pricing['accommodation_tiered_discount'] ?? false);
        $nightDiscounts  = $pricing['accommodation_night_discounts'] ?? [];
        $nightTiers      = $pricing['accommodation_night_tiers'] ?? [];
        $tierNightCounts = collect($nightTiers)
            ->filter(fn ($tier) => \App\Services\AccommodationDiscountTierEngine::tierHasDiscount($tier))
            ->countBy(fn ($tier) => \App\Services\AccommodationDiscountTierEngine::tierType($tier)
                . '|' . ($tier['pay_amount'] ?? '')
                . '|' . ($tier['discount_percentage'] ?? ''));
        if ($tierNightCounts->isEmpty() && !$isTieredAcc) {
            $tierNightCounts = collect($nightDiscounts)
                ->filter(fn ($pct) => (int) $pct > 0)
                ->countBy()
                ->sortKeysDesc();
        }
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
                <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['room_subtotal'])) }} ریال</span>
            </div>

            @if(($pricing['extra_guests_total'] ?? 0) > 0)
            <div class="d-flex justify-content-between py-1">
                <span class="text-muted">کف‌خواب ({{ $this->totalExtraGuests }} نفر)</span>
                <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['extra_guests_total'])) }} ریال</span>
            </div>
            @endif

            @if(($pricing['children_under_6'] ?? 0) > 0 && ($pricing['children_discount_amount'] ?? 0) > 0)
            <div class="d-flex justify-content-between py-1 text-success">
                <span class="text-muted">
                    تخفیف کودک زیر ۶ سال
                    <span class="text-secondary">({{ $pricing['children_under_6'] }} نفر · {{ $pricing['children_under_6_discount_percentage'] ?? $accommodation->childrenUnder6DiscountPercentage() }}٪ نرخ)</span>
                </span>
                <span>− {{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['children_discount_amount'])) }} ریال</span>
            </div>
            @endif

            @if($accDiscountAmt > 0)
            <div class="py-1 text-danger">
                <div class="d-flex justify-content-between">
                    <span>
                        <i class="bi bi-tag-fill me-1" style="font-size:.75rem"></i>تخفیف اقامت
                        @if($isTieredAcc)
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-1" style="font-size:.65rem">پلکانی</span>
                        @elseif(count($accDiscountBreakdown) === 1)
                            ({{ $accDiscountBreakdown[0]['discount_percentage'] ?? $accDiscountPct }}٪)
                        @elseif($accDiscountPct > 0)
                            ({{ $accDiscountPct }}٪)
                        @endif
                        @if(($pricing['veteran_discount_nights'] ?? 0) > 0 && ($pricing['veteran_discount_nights'] ?? 0) < ($pricing['nights'] ?? 0))
                        <br><span class="text-muted ms-3" style="font-size:.75rem">
                            {{ $pricing['veteran_discount_nights'] }} شب با تخفیف ایثارگری از {{ $pricing['nights'] }} شب
                            @if($fullRateNights = ($pricing['nights'] ?? 0) - ($pricing['veteran_discount_nights'] ?? 0))
                                · {{ $fullRateNights }} شب نرخ عادی
                            @endif
                        </span>
                        @endif
                        @if($isTieredAcc && $tierNightCounts->isNotEmpty())
                        <br><span class="text-muted ms-3" style="font-size:.75rem">
                            @foreach($tierNightCounts as $tierKey => $count)
                                @php
                                    [$tierType, $payAmount, $pct] = array_pad(explode('|', (string) $tierKey, 3), 3, '');
                                @endphp
                                <span class="d-inline-block me-2">
                                    {{ $count }} شب ×
                                    @if($tierType === 'fixed_pay')
                                        مبلغ ثابت {{ \App\Support\PdfPersian::toPersianDigits(number_format((int) $payAmount)) }} ریال
                                    @elseif($tierType === 'free')
                                        رایگان
                                    @else
                                        {{ is_numeric($tierKey) ? $tierKey : $pct }}٪
                                    @endif
                                </span>
                            @endforeach
                        </span>
                        @endif
                        @if($nonDiscountGuests > 0)
                        <br><span class="text-muted ms-3" style="font-size:.75rem">{{ $nonDiscountGuests }} نفر بدون تخفیف ایثارگری</span>
                        @endif
                    </span>
                    @if(empty($accDiscountBreakdown))
                    <span class="fw-semibold">− {{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['veteran_accommodation_discount_amount'] ?? $accDiscountAmt)) }} ریال</span>
                    @endif
                </div>
                <x-booking.accommodation-discount-breakdown
                    :breakdown="$accDiscountBreakdown"
                    :total="$pricing['veteran_accommodation_discount_amount'] ?? $accDiscountAmt"
                    :tiered="$isTieredAcc"
                    compact
                />
            </div>
            @endif

            @if(($pricing['manual_accommodation_discount_amount'] ?? 0) > 0)
            <div class="d-flex justify-content-between py-1 text-danger">
                <span>
                    <i class="bi bi-sliders me-1" style="font-size:.75rem"></i>تخفیف دستی اقامت (مهمانان نرخ عادی)
                </span>
                <span class="fw-semibold">− {{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['manual_accommodation_discount_amount'])) }} ریال</span>
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
                            <span class="text-secondary">({{ $line['quantity'] }} × {{ \App\Support\PdfPersian::toPersianDigits(number_format($line['unit_price'])) }})</span>
                        </span>
                        <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($line['line_subtotal'])) }} ریال</span>
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
                    <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['subtotal_before_discount'])) }} ریال</span>
                </div>
                @if(($pricing['discount_amount'] ?? 0) > 0)
                <div class="d-flex justify-content-between text-danger py-1">
                    <span>
                        <i class="bi bi-scissors me-1"></i>مجموع تخفیفات
                        @if($pricing['discount_percentage'] > 0)
                        <span class="badge bg-danger bg-opacity-15 text-danger ms-1" style="font-size:.72rem">{{ $pricing['discount_percentage'] }}٪</span>
                        @endif
                    </span>
                    <span class="fw-semibold">− {{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['discount_amount'])) }} ریال</span>
                </div>
                @endif
                @php $platformCommission = (int) ($pricing['platform_commission_amount'] ?? 0); @endphp
                @if($platformCommission > 0)
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">کارمزد سامانه</span>
                    <span>{{ \App\Support\PdfPersian::toPersianDigits(number_format($platformCommission)) }} ریال</span>
                </div>
                @endif
                <div class="d-flex justify-content-between fw-bold pt-1 mt-1 border-top">
                    <span>مبلغ قابل پرداخت</span>
                    <span class="text-primary fs-6">{{ \App\Support\PdfPersian::toPersianDigits(number_format($pricing['total_price'])) }} ریال</span>
                </div>
            </div>

        </div>
    </div>
    @else
    <div class="card shadow-sm border mb-3">
        <div class="card-header bg-white py-2 px-3 d-flex align-items-center gap-2">
            <i class="bi bi-receipt text-muted"></i>
            <span class="small fw-semibold text-muted">پیش‌نمایش محاسبه هزینه</span>
        </div>
        <div class="card-body py-3 px-3 text-muted small">
            پس از انتخاب اتاق و تاریخ، پیش‌نمایش هزینه اینجا نمایش داده می‌شود.
        </div>
    </div>
    @endif

    {{-- Navigation --}}
    <div id="manual-booking-nav" class="d-flex gap-2">
        @if($step > 1 && $step < 5)
        <button type="button" wire:click="prevStep" data-mbf-slide="-1" class="btn btn-outline-secondary flex-fill"><i class="bi bi-arrow-right me-1"></i>قبلی</button>
        @endif

        @if($step < 4)
        <button type="button" wire:click="nextStep" data-mbf-slide="1" class="btn btn-primary flex-fill">بعدی <i class="bi bi-arrow-left ms-1"></i></button>
        @elseif($step === 4)
        <button type="button"
                data-mbf-slide="1"
                class="btn btn-success flex-fill"
                data-bnb-price-change
                data-bnb-price-action="submitManualBooking"
                data-bnb-price-params="{}"
                wire:loading.attr="disabled"
                wire:target="executeConfirmedPriceChange,previewBookingPriceChange,submit">
            <span wire:loading.remove wire:target="executeConfirmedPriceChange,previewBookingPriceChange,submit"><i class="bi bi-check-circle me-1"></i>ثبت رزرو و صدور فیش</span>
            <span wire:loading wire:target="executeConfirmedPriceChange,previewBookingPriceChange,submit">در حال ثبت...</span>
        </button>
        @endif
    </div>
            </div>{{-- /.mbf-aside-sticky --}}
        </div>{{-- /.mbf-layout-aside --}}
        @endif
    </div>{{-- /.row --}}

    <div id="bnb-payment-doc-anchor" class="d-none">
        <div id="bnb-payment-doc-slot" class="d-none">
            <label class="form-label small">مستندات پرداخت (اختیاری)</label>
            <input type="file"
                   wire:model="pendingPaymentDocuments"
                   multiple
                   accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*"
                   class="form-control form-control-sm">
            <div wire:loading wire:target="pendingPaymentDocuments" class="small text-muted mt-1">در حال بارگذاری فایل...</div>
            @error('pendingPaymentDocuments')<div class="text-danger small">{{ $message }}</div>@enderror
            @error('pendingPaymentDocuments.*')<div class="text-danger small">{{ $message }}</div>@enderror
            @if($pendingPaymentDocuments !== [])
            <div class="small text-success mt-1">
                @foreach($pendingPaymentDocuments as $doc)
                <div><i class="bi bi-check-circle me-1"></i>{{ $doc->getClientOriginalName() }}</div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    @if($showAddPosTerminal)
    <div class="modal-backdrop fade show" style="z-index:10100;"></div>
    <div class="modal fade show" style="display:block;z-index:10105;" tabindex="-1" wire:keydown.escape="closePosTerminalModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-top:4px solid #0ea5e9;">
                <div class="modal-header">
                    <h5 class="modal-title">افزودن ترمینال پز</h5>
                    <button type="button" class="btn-close" wire:click="closePosTerminalModal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        ترمینال‌ها در سطح استان ثبت می‌شوند و در ثبت پرداخت رزرو قابل انتخاب هستند.
                    </div>
                    <div class="row g-2">
                        <x-accounting.province-select
                            class="col-12"
                            :provinces="$provinces ?? collect()"
                            :show-code-preview="false"
                            hint="پیش‌فرض از استان اقامتگاه است."
                        />
                        <div class="col-md-6">
                            <label class="form-label small">شماره ترمینال <span class="text-danger">*</span></label>
                            <input type="text" wire:model="newPosTerminalNumber" dir="ltr" class="form-control form-control-sm">
                            @error('newPosTerminalNumber')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">عنوان (اختیاری)</label>
                            <input type="text" wire:model="newPosTerminalLabel" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" wire:click="closePosTerminalModal" class="btn btn-outline-secondary">انصراف</button>
                    <button type="button" wire:click="addPosTerminalToCatalog" class="btn btn-info text-white">
                        <i class="bi bi-check-lg me-1"></i>ذخیره و انتخاب
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
