@props([
    'accommodations',
    'cities',
    'hasActiveFilters' => false,
    'showCityFilter' => true,
])

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center justify-content-between" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#bookingFilterBody">
        <span class="fw-semibold small"><i class="bi bi-funnel me-1"></i>فیلترها</span>
        @if($hasActiveFilters)
            <span class="badge bg-primary">فعال</span>
        @else
            <i class="bi bi-chevron-down text-muted" style="font-size:.8rem"></i>
        @endif
    </div>
    <div class="collapse show" id="bookingFilterBody">
        <div class="card-body pb-2 pt-3">
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
                        <select wire:model="draftAccommodationId" class="form-select form-select-sm">
                            <option value="">همه اقامتگاه‌ها</option>
                            @foreach($accommodations as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($showCityFilter)
                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">شهر</label>
                        <select wire:model="draftCityId" class="form-select form-select-sm">
                            <option value="">همه شهرها</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}">{{ $city->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

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
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">مبلغ از (تومان)</label>
                        <input type="number" wire:model="draftPriceMin" class="form-control form-control-sm" min="0" placeholder="مثلاً ۵۰۰۰۰۰">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">مبلغ تا (تومان)</label>
                        <input type="number" wire:model="draftPriceMax" class="form-control form-control-sm" min="0" placeholder="مثلاً ۵۰۰۰۰۰۰">
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

                    <div class="col-12 d-flex gap-2 justify-content-end mt-1">
                        <button type="button" wire:click="resetFilters" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>پاک کردن
                        </button>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-funnel me-1"></i>اعمال فیلتر
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
