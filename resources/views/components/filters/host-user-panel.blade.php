@props([
    'accommodations',
    'veteranOptions',
    'hasActiveFilters' => false,
])

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center justify-content-between" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#hostUserFilterBody">
        <span class="fw-semibold small"><i class="bi bi-funnel me-1"></i>فیلترها</span>
        @if($hasActiveFilters)
            <span class="badge bg-primary">فعال</span>
        @else
            <i class="bi bi-chevron-down text-muted" style="font-size:.8rem"></i>
        @endif
    </div>
    <div class="collapse show" id="hostUserFilterBody">
        <div class="card-body pb-2 pt-3">
            <form wire:submit="applyUserFilters">
                <div class="row g-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">جستجو</label>
                        <input type="text" wire:model="draftSearch" class="form-control form-control-sm" placeholder="نام / موبایل / کد ملی">
                    </div>

                    <div class="col-6 col-md-3">
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

                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">اقامتگاه</label>
                        <select wire:model="draftAccommodationId" class="form-select form-select-sm">
                            <option value="">همه اقامتگاه‌ها</option>
                            @foreach($accommodations as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">حداقل رزرو</label>
                        <input type="number" wire:model="draftBookingsMin" class="form-control form-control-sm" min="1" placeholder="مثلاً ۲">
                    </div>

                    <div class="col-12 d-flex gap-2 justify-content-end mt-1">
                        <button type="button" wire:click="resetUserFilters" class="btn btn-sm btn-outline-secondary">
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
