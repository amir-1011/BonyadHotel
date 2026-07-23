<div id="program-form-root">

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('host.programs.show', $program) }}" class="btn btn-sm btn-outline-secondary" wire:navigate><i class="bi bi-arrow-right"></i></a>
</div>

@if($errors->any())
<div class="alert alert-danger"><ul class="mb-0 pe-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="row g-3">
<div class="col-lg-8">

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold bg-success text-white py-2">
            <i class="bi bi-info-circle me-1"></i> اطلاعات پایه برنامه
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">اقامتگاه</label>
                    <input type="text" class="form-control" readonly value="{{ $program->accommodation->name }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">نوع برنامه <span class="text-danger">*</span></label>
                    <select wire:model="programType" class="form-select">
                        <option value="camp">اردو</option>
                        <option value="event">رویداد</option>
                        <option value="other">سایر</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">عنوان برنامه <span class="text-danger">*</span></label>
                    <input type="text" wire:model="title" class="form-control @error('title') is-invalid @enderror" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @include('host.programs._date_fields')
                <div class="col-md-4">
                    <label class="form-label">تعداد نفرات <span class="text-danger">*</span></label>
                    <input type="number" wire:model="guestCount" class="form-control" min="1" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">تعداد اتاق‌های رزرو شده <span class="text-danger">*</span></label>
                    <input type="number" wire:model="roomsAllocated" class="form-control" min="1" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">وضعیت</label>
                    <select wire:model="status" class="form-select">
                        <option value="active">فعال</option>
                        <option value="completed">پایان‌یافته</option>
                        <option value="cancelled">لغو‌شده</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">توضیحات</label>
                    <textarea wire:model="description" class="form-control" rows="2"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold bg-warning bg-opacity-75 py-2">
            <i class="bi bi-cash-stack me-1"></i> اطلاعات مالی
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">مبلغ کل (ریال) <span class="text-danger">*</span></label>
                    <x-money-input wire:model="totalAmount" class="form-control" min="0" required />
                </div>
                <div class="col-md-4">
                    <label class="form-label">بیعانه (ریال)</label>
                    <x-money-input wire:model="depositAmount" class="form-control" min="0" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">مبلغ تخفیف (ریال)</label>
                    <x-money-input wire:model="discountAmount" class="form-control" min="0" />
                </div>
                <div class="col-md-4">
                    <label class="form-label">درصد تخفیف</label>
                    <div class="input-group">
                        <input type="number" wire:model="discountPercentage" class="form-control" min="0" max="100">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">کارفرما</label>
                    <input type="text" wire:model="employer" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">پیمانکار</label>
                    <input type="text" wire:model="contractor" class="form-control">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label">یادداشت داخلی</label>
            <textarea wire:model="notes" class="form-control" rows="3"></textarea>
        </div>
    </div>

</div>

<div class="col-lg-4">
    <div class="card shadow-sm mb-3 border-danger" x-data="{ open: $wire.isSupportiveService }">
        <div class="card-header fw-semibold bg-danger text-white py-2">
            <i class="bi bi-heart-fill me-1"></i> خدمات حمایتی بنیاد شهید
        </div>
        <div class="card-body">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" wire:model.live="isSupportiveService"
                       x-model="open" id="isSupportive">
                <label class="form-check-label fw-semibold" for="isSupportive">این برنامه دارای خدمت حمایتی است</label>
            </div>
            <div x-show="open">
                <label class="form-label">نوع خدمت حمایتی</label>
                <input type="text" wire:model="supportiveServiceType" class="form-control">
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold py-2">
            <i class="bi bi-door-open me-1"></i> اتاق‌های اختصاص داده‌شده
        </div>
        <div class="card-body">
            @forelse($roomTypes as $rt)
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2 p-2 rounded" style="background:#f8fff8;">
                <div class="form-check flex-grow-1">
                    <input class="form-check-input" type="checkbox"
                           wire:model="selectedRoomTypes.{{ $rt->id }}"
                           id="rt{{ $rt->id }}">
                    <label class="form-check-label" for="rt{{ $rt->id }}">
                        {{ $rt->name }} <small class="text-muted">({{ $rt->room_count }} اتاق)</small>
                    </label>
                </div>
            </div>
            @empty
            <p class="text-muted small mb-0">اتاقی تعریف نشده</p>
            @endforelse
        </div>
    </div>

    <button type="button" wire:click="update" onclick="window.syncProgramDates && window.syncProgramDates()" wire:loading.attr="disabled" class="btn btn-warning w-100 fw-semibold">
        <span wire:loading.remove><i class="bi bi-check-circle me-1"></i> ذخیره تغییرات</span>
        <span wire:loading>در حال ذخیره...</span>
    </button>
</div>
</div>

</div>

