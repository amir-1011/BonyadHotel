
<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('host.programs.index') }}" class="btn btn-sm btn-outline-secondary" wire:navigate><i class="bi bi-arrow-right"></i></a>
    <h5 class="fw-bold mb-0"><i class="bi bi-flag-fill me-2 text-success"></i>ثبت برنامه / اردوی جدید</h5>
</div>

@if($errors->any())
<div class="alert alert-danger"><ul class="mb-0 pe-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="row g-3">
{{-- ستون اصلی --}}
<div class="col-lg-8">

    {{-- اطلاعات پایه --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold bg-success text-white py-2">
            <i class="bi bi-info-circle me-1"></i> اطلاعات پایه برنامه
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">اقامتگاه <span class="text-danger">*</span></label>
                    <select wire:model.live="accommodationId" class="form-select @error('accommodationId') is-invalid @enderror" required>
                        <option value="0">-- انتخاب اقامتگاه --</option>
                        @foreach($myAccommodations as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                        @endforeach
                    </select>
                    @error('accommodationId')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                    <input type="text" wire:model="title" class="form-control @error('title') is-invalid @enderror"
                           placeholder="مثلاً: اردوی جانبازان شیمیایی" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">تاریخ شروع <span class="text-danger">*</span></label>
                    <input type="date" wire:model="startDate" class="form-control @error('startDate') is-invalid @enderror" required>
                    @error('startDate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">تاریخ پایان <span class="text-danger">*</span></label>
                    <input type="date" wire:model="endDate" class="form-control @error('endDate') is-invalid @enderror" required>
                    @error('endDate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">تعداد نفرات <span class="text-danger">*</span></label>
                    <input type="number" wire:model="guestCount" class="form-control" min="1" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">تعداد اتاق‌های رزرو شده <span class="text-danger">*</span></label>
                    <input type="number" wire:model="roomsAllocated" class="form-control" min="1" required>
                </div>
                <div class="col-12">
                    <label class="form-label">توضیحات</label>
                    <textarea wire:model="description" class="form-control" rows="2" placeholder="توضیحات اختیاری..."></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- اطلاعات مالی --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold bg-warning bg-opacity-75 py-2">
            <i class="bi bi-cash-stack me-1"></i> اطلاعات مالی
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">مبلغ کل (ریال) <span class="text-danger">*</span></label>
                    <input type="number" wire:model="totalAmount" class="form-control" min="0" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">بیعانه (ریال)</label>
                    <input type="number" wire:model="depositAmount" class="form-control" min="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">مبلغ تخفیف (ریال)</label>
                    <input type="number" wire:model="discountAmount" class="form-control" min="0">
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
                    <input type="text" wire:model="employer" class="form-control" placeholder="نام کارفرما">
                </div>
                <div class="col-md-4">
                    <label class="form-label">پیمانکار</label>
                    <input type="text" wire:model="contractor" class="form-control" placeholder="نام پیمانکار">
                </div>
            </div>
        </div>
    </div>

    {{-- یادداشت --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label">یادداشت داخلی</label>
            <textarea wire:model="notes" class="form-control" rows="3" placeholder="یادداشت‌های داخلی..."></textarea>
        </div>
    </div>

</div>

{{-- ستون کنار --}}
<div class="col-lg-4">

    {{-- خدمات حمایتی --}}
    <div class="card shadow-sm mb-3 border-danger" x-data="{ open: $wire.isSupportiveService }">
        <div class="card-header fw-semibold bg-danger text-white py-2">
            <i class="bi bi-heart-fill me-1"></i> خدمات حمایتی بنیاد شهید
        </div>
        <div class="card-body">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" wire:model.live="isSupportiveService"
                       x-model="open" id="isSupportive">
                <label class="form-check-label fw-semibold" for="isSupportive">
                    این برنامه دارای خدمت حمایتی است
                </label>
            </div>
            <div x-show="open">
                <label class="form-label">نوع خدمت حمایتی</label>
                <input type="text" wire:model="supportiveServiceType" class="form-control"
                       placeholder="مثلاً: تخفیف جانبازان شیمیایی">
                <div class="form-text text-muted mt-1">
                    این تخفیف در گزارش سالانه خدمات حمایتی بنیاد شهید محاسبه می‌شود.
                </div>
            </div>
        </div>
    </div>

    {{-- اتاق‌ها --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold py-2">
            <i class="bi bi-door-open me-1"></i> اتاق‌های اختصاص داده‌شده
        </div>
        <div class="card-body">
            @forelse($roomTypes as $rt)
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2" style="background:#f8fff8;border-radius:8px;padding:.5rem .75rem;">
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
            <div class="text-muted small text-center py-2">
                ابتدا اقامتگاه را انتخاب کنید
            </div>
            @endforelse
        </div>
    </div>

    <button type="button" wire:click="store" wire:loading.attr="disabled" class="btn btn-success w-100">
        <span wire:loading.remove><i class="bi bi-check-circle me-1"></i> ثبت برنامه</span>
        <span wire:loading>در حال ذخیره...</span>
    </button>
</div>
</div>

</div>

