@props([
    'accommodations',
    'provinces' => collect(),
    'veteranOptions',
    'userTypeOptions' => [],
    'hasBookingsOptions' => [],
    'sortOptions' => [],
    'hasActiveFilters' => false,
    'countFiltered' => null,
])

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
        <span class="fw-semibold small" role="button" data-bs-toggle="collapse" data-bs-target="#hostUserFilterBody">
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
    <div class="collapse show" id="hostUserFilterBody">
        <div class="card-body py-2">
            <form wire:submit="applyUserFilters">
                <div class="row g-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">جستجو</label>
                        <input type="text" wire:model="draftSearch" class="form-control form-control-sm" placeholder="نام / موبایل / کد ملی / کد حسابداری">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">نوع کاربر</label>
                        <select wire:model="draftUserType" class="form-select form-select-sm">
                            <option value="">همه</option>
                            @foreach($userTypeOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($provinces->isNotEmpty())
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">استان</label>
                        <select wire:model="draftProvinceId" class="form-select form-select-sm">
                            <option value="">همه استان‌ها</option>
                            @foreach($provinces as $province)
                                <option value="{{ $province->id }}">
                                    {{ $province->name }}
                                    @if($province->accounting_code)
                                        ({{ $province->accounting_code }})
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">گروه ایثارگری</label>
                        <select wire:model="draftVeteranType" class="form-select form-select-sm">
                            <option value="">همه</option>
                            <option value="__none__">کاربر عادی</option>
                            @foreach($veteranOptions as $key => $option)
                                @if($key !== '')
                                    <option value="{{ $key }}">{{ $option['label'] ?? $key }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">اقامتگاه</label>
                        <select wire:model="draftAccommodationId" class="form-select form-select-sm">
                            <option value="">همه اقامتگاه‌ها</option>
                            @foreach($accommodations as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">وضعیت رزرو</label>
                        <select wire:model="draftHasBookings" class="form-select form-select-sm">
                            <option value="">همه</option>
                            @foreach($hasBookingsOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">حداقل رزرو</label>
                        <input type="number" wire:model="draftBookingsMin" class="form-control form-control-sm" min="1" placeholder="مثلاً ۲">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">مرتب‌سازی</label>
                        <select wire:model="draftSort" class="form-select form-select-sm">
                            <option value="">پیش‌فرض</option>
                            @foreach($sortOptions as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 col-md-6 d-flex gap-2 justify-content-start align-items-end flex-wrap pb-1">
                        @if($countFiltered !== null)
                            <x-filters.summary-stats :count-filtered="$countFiltered" />
                        @endif
                        <div class="d-flex gap-2 ms-auto">
                            <button type="button" wire:click="resetUserFilters" class="btn btn-sm btn-outline-secondary">
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
