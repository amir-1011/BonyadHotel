@props([
    'accommodations',
    'provinces' => collect(),
    'cities',
    'counties' => collect(),
    'serviceCatalogs' => collect(),
    'serviceVariants' => collect(),
    'roomCategories' => collect(),
    'rooms' => collect(),
    'veteranOptions' => [],
    'showServiceAccommodation' => true,
    'showRoomAccommodation' => true,
    'draftProvinceId' => '',
    'draftAccommodationId' => '',
    'draftServiceCatalogId' => '',
    'draftRoomCategory' => '',
    'draftBookingSource' => '',
    'hasActiveFilters' => false,
    'showCityFilter' => true,
    'showCountyFilter' => true,
    'showHostFilter' => false,
    'showReserverFilter' => false,
    'hosts' => collect(),
    'reservers' => collect(),
    'employers' => collect(),
    'countFiltered' => null,
    'totalFiltered' => null,
])

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
        <span class="fw-semibold small" role="button" data-bs-toggle="collapse" data-bs-target="#bookingFilterBody">
            <i class="bi bi-funnel me-1"></i>فیلترها
            @unless($hasActiveFilters)
            <i class="bi bi-chevron-down text-muted" style="font-size:.8rem"></i>
            @endunless
        </span>
        <div class="d-flex align-items-center gap-2">
            {{ $actions ?? '' }}
            @if($hasActiveFilters)
                <span class="badge bg-primary">فعال</span>
            @endif
        </div>
    </div>
    <div class="collapse show" id="bookingFilterBody">
        <div class="card-body py-2">
            <form wire:submit="applyFilters" id="booking-filter-form">
                <div class="row g-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">جستجو</label>
                        <input type="text" wire:model="draftSearch" class="form-control form-control-sm" placeholder="کد رزرو / نام / موبایل / اقامتگاه">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">وضعیت</label>
                        <select wire:model="draftStatus" class="form-select form-select-sm">
                            <option value="">همه</option>
                            <option value="pending">در انتظار</option>
                            <option value="confirmed">تأیید شده</option>
                            <option value="cancelled">لغو شده</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">اقامتگاه</label>
                        <select wire:model.live="draftAccommodationId" class="form-select form-select-sm" wire:key="booking-filter-accommodation">
                            <option value="">همه اقامتگاه‌ها</option>
                            @foreach($accommodations as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($showHostFilter)
                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">میزبان</label>
                        <select wire:model="draftHostId" class="form-select form-select-sm">
                            <option value="">همه میزبان‌ها</option>
                            @foreach($hosts as $host)
                                <option value="{{ $host->id }}">{{ $host->name ?? $host->mobile }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    @if($showReserverFilter)
                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">رزرو کننده</label>
                        <select wire:model="draftReserverId" class="form-select form-select-sm" wire:key="booking-filter-reserver-{{ $draftAccommodationId }}" @if($draftBookingSource === 'online') disabled @endif>
                            <option value="">{{ $draftBookingSource === 'online' ? 'فقط رزرو حضوری' : ($draftAccommodationId ? 'همه رزروکنندگان این اقامتگاه' : 'همه رزروکنندگان') }}</option>
                            @foreach($reservers as $reserver)
                                <option value="{{ $reserver->id }}">{{ $reserver->name ?? $reserver->mobile }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="col-12">
                        <div class="small text-muted fw-semibold border-bottom pb-1 mb-1">
                            <i class="bi bi-geo-alt me-1"></i>مکان اقامتگاه
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">استان</label>
                        <select wire:model.live="draftProvinceId" class="form-select form-select-sm" wire:key="booking-filter-province">
                            <option value="">همه استان‌ها</option>
                            @foreach($provinces as $province)
                                <option value="{{ $province->id }}">{{ $province->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($showCountyFilter)
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">شهرستان</label>
                        <select wire:model="draftCountyId" class="form-select form-select-sm" wire:key="booking-filter-county-{{ $draftProvinceId }}" @disabled(!$draftProvinceId)>
                            <option value="">{{ $draftProvinceId ? 'همه شهرستان‌ها' : 'ابتدا استان انتخاب کنید' }}</option>
                            @foreach($counties as $county)
                                <option value="{{ $county->id }}">{{ $county->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    @if($showCityFilter)
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">شهر</label>
                        <select wire:model.live="draftCityId" class="form-select form-select-sm" wire:key="booking-filter-city-{{ $draftProvinceId }}" @disabled(!$draftProvinceId)>
                            <option value="">{{ $draftProvinceId ? 'همه شهرها' : 'ابتدا استان انتخاب کنید' }}</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}">{{ $city->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="col-12">
                        <div class="small text-muted fw-semibold border-bottom pb-1 mb-1">
                            <i class="bi bi-door-open me-1"></i>اتاق و رزرو
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">نوع اتاق</label>
                        <select wire:model.live="draftRoomCategory" class="form-select form-select-sm" wire:key="booking-filter-room-category-{{ $draftAccommodationId }}-{{ $draftProvinceId }}">
                            <option value="">{{ $draftAccommodationId ? 'همه انواع این اقامتگاه' : 'همه انواع اتاق' }}</option>
                            @foreach($roomCategories as $category)
                                <option value="{{ $category }}">{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">نام اتاق (فیزیکی)</label>
                        <select wire:model="draftRoomId" class="form-select form-select-sm" wire:key="booking-filter-room-{{ $draftRoomCategory }}-{{ $draftAccommodationId }}">
                            <option value="">{{ $draftRoomCategory ? 'همه اتاق‌های این نوع' : ($draftAccommodationId ? 'همه اتاق‌های این اقامتگاه' : 'ابتدا نوع اتاق را انتخاب کنید') }}</option>
                            @foreach($rooms as $room)
                                <option value="{{ $room->id }}">
                                    {{ $room->roomType ? $room->roomType->groupLabel() . ' · ' . $room->name : $room->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">گروه ایثارگری مهمان</label>
                        <select wire:model="draftVeteranType" class="form-select form-select-sm">
                            <option value="">همه</option>
                            <option value="__none__">عادی (بدون تخفیف ایثارگری)</option>
                            @foreach($veteranOptions as $key => $option)
                                @if($key !== '')
                                    <option value="{{ $key }}">{{ $option['label'] ?? $key }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">نوع رزرو</label>
                        <select wire:model.live="draftBookingSource" class="form-select form-select-sm">
                            <option value="">همه</option>
                            <option value="manual">حضوری</option>
                            <option value="online">اینترنتی</option>
                            <option value="program">برنامه / اردو</option>
                        </select>
                    </div>

                    @if($draftBookingSource === 'program')
                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">نوع برنامه</label>
                        <select wire:model="draftProgramType" class="form-select form-select-sm">
                            <option value="">همه</option>
                            @foreach(\App\Models\Program::typeOptions() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">نوع پرداخت برنامه</label>
                        <select wire:model="draftProgramPaymentType" class="form-select form-select-sm">
                            <option value="">همه</option>
                            @foreach(\App\Models\Program::paymentTypeOptions() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">کارفرمای برنامه</label>
                        <select wire:model="draftProgramEmployerId" class="form-select form-select-sm">
                            <option value="">همه کارفرمایان</option>
                            @foreach($employers as $employer)
                                <option value="{{ $employer->id }}">{{ $employer->displayLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="col-12">
                        <div class="small text-muted fw-semibold border-bottom pb-1 mb-1">
                            <i class="bi bi-diagram-3 me-1"></i>خدمات رزرو
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">خدمت والد</label>
                        <select wire:model.live="draftServiceCatalogId" class="form-select form-select-sm" wire:key="booking-filter-service-{{ $draftAccommodationId }}-{{ $draftProvinceId }}">
                            <option value="">{{ $draftAccommodationId ? 'همه خدمات این اقامتگاه' : 'همه خدمات' }}</option>
                            @foreach($serviceCatalogs as $svc)
                                <option value="{{ $svc->id }}">
                                    {{ $showServiceAccommodation ? ($svc->accommodation?->name . ' — ' . $svc->name) : $svc->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">زیرشاخه / نوع خدمت</label>
                        <select wire:model="draftServiceCatalogVariantId" class="form-select form-select-sm" wire:key="booking-filter-variant-{{ $draftServiceCatalogId }}" @disabled(!$draftServiceCatalogId)>
                            <option value="">{{ $draftServiceCatalogId ? 'همه انواع این خدمت' : 'ابتدا خدمت والد را انتخاب کنید' }}</option>
                            @foreach($serviceVariants as $variant)
                                <option value="{{ $variant->id }}">{{ $variant->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">ورود از</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="booking-draft-check-in-from" data-wire-prop="draftCheckInFrom" wire:model="draftCheckInFrom" class="form-control form-control-sm jalali-picker-booking" autocomplete="off" placeholder="۱۴۰۳/۰۲/۰۱">
                            <button type="button" class="btn btn-outline-secondary booking-clear-date" data-target="booking-draft-check-in-from" tabindex="-1" title="پاک کردن"><i class="bi bi-x"></i></button>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">ورود تا</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="booking-draft-check-in-to" data-wire-prop="draftCheckInTo" wire:model="draftCheckInTo" class="form-control form-control-sm jalali-picker-booking" autocomplete="off" placeholder="۱۴۰۳/۰۲/۳۱">
                            <button type="button" class="btn btn-outline-secondary booking-clear-date" data-target="booking-draft-check-in-to" tabindex="-1" title="پاک کردن"><i class="bi bi-x"></i></button>
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">خروج از</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="booking-draft-check-out-from" data-wire-prop="draftCheckOutFrom" wire:model="draftCheckOutFrom" class="form-control form-control-sm jalali-picker-booking" autocomplete="off" placeholder="۱۴۰۳/۰۳/۰۱">
                            <button type="button" class="btn btn-outline-secondary booking-clear-date" data-target="booking-draft-check-out-from" tabindex="-1" title="پاک کردن"><i class="bi bi-x"></i></button>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">خروج تا</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="booking-draft-check-out-to" data-wire-prop="draftCheckOutTo" wire:model="draftCheckOutTo" class="form-control form-control-sm jalali-picker-booking" autocomplete="off" placeholder="۱۴۰۳/۰۳/۳۱">
                            <button type="button" class="btn btn-outline-secondary booking-clear-date" data-target="booking-draft-check-out-to" tabindex="-1" title="پاک کردن"><i class="bi bi-x"></i></button>
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">شب‌ها (از)</label>
                        <input type="number" wire:model="draftNightsMin" class="form-control form-control-sm" min="1" placeholder="مثلاً ۱">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">شب‌ها (تا)</label>
                        <input type="number" wire:model="draftNightsMax" class="form-control form-control-sm" min="1" placeholder="مثلاً ۷">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">مبلغ از (ریال)</label>
                        <x-money-input wire:model="draftPriceMin" class="form-control form-control-sm" min="0" placeholder="مثلاً ۵۰۰,۰۰۰" />
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">مبلغ تا (ریال)</label>
                        <x-money-input wire:model="draftPriceMax" class="form-control form-control-sm" min="0" placeholder="مثلاً ۵,۰۰۰,۰۰۰" />
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">حداقل مهمان</label>
                        <input type="number" wire:model="draftGuestsMin" class="form-control form-control-sm" min="1" placeholder="مثلاً ۲">
                    </div>

                    <div class="col-6 col-md-2 d-flex align-items-end pb-1">
                        <div class="form-check">
                            <input type="checkbox" wire:model="draftHasDiscount" value="1" id="chkBookingDiscount" class="form-check-input">
                            <label for="chkBookingDiscount" class="form-check-label small">فقط تخفیف‌دار</label>
                        </div>
                    </div>

                    <div class="col-12 d-flex gap-2 justify-content-between align-items-center flex-wrap mt-1">
                        @if($countFiltered !== null)
                            <x-filters.summary-stats :count-filtered="$countFiltered" :total-filtered="$totalFiltered" />
                        @endif
                        <div class="d-flex gap-2 ms-auto">
                            <button type="button" wire:click="resetFilters" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i>پاک کردن
                            </button>
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="bi bi-funnel me-1"></i>اعمال فیلتر
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
