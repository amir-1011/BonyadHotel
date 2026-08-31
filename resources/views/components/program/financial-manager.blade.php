@props([
    'program',
    'panel' => 'host',
])

@php
    $totalBeneficiaryDebt = (int) $program->beneficiaryCosts->sum('debt_amount');
    $canEdit = $this->canEditProgramFinancial();
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold bg-warning bg-opacity-75 py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-cash-stack me-1"></i>اطلاعات مالی (ریال)</span>
        @if($canEdit)
            <div class="d-flex align-items-center gap-2">
                @if($financialEditMode)
                <button type="button"
                        wire:click="saveProgramFinancial"
                        class="btn btn-sm btn-success"
                        wire:loading.attr="disabled"
                        wire:target="saveProgramFinancial"
                        data-swal-confirm="اطلاعات مالی برنامه ذخیره شود؟ مبلغ کل و رزرو مرتبط به‌روز می‌شود.">
                    <span wire:loading.remove wire:target="saveProgramFinancial"><i class="bi bi-check-lg me-1"></i>ذخیره</span>
                    <span wire:loading wire:target="saveProgramFinancial" class="spinner-border spinner-border-sm"></span>
                </button>
                <button type="button" wire:click="toggleFinancialEditMode" class="btn btn-sm btn-outline-secondary">انصراف</button>
                @else
                <button type="button" wire:click="toggleFinancialEditMode" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil-square me-1"></i>ویرایش مبالغ
                </button>
                @endif
            </div>
        @endif
    </div>
    <div class="card-body">
        @if($financialEditMode)
        <div class="alert alert-info small py-2">
            <i class="bi bi-info-circle me-1"></i>
            قیمت پایه، تخفیف و بیعانه را ویرایش کنید. جمع خدمات از رزرو خوانده می‌شود و مبلغ کل خودکار محاسبه می‌شود.
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label small mb-1">قیمت پایه</label>
                <x-money-input wire:model.live="editBasePrice" class="form-control form-control-sm @error('editBasePrice') is-invalid @enderror" min="0" />
                @error('editBasePrice')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">جمع خدمات</label>
                <input type="text" class="form-control form-control-sm" readonly value="{{ \App\Support\PdfPersian::toPersianDigits(number_format($program->services_subtotal)) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">مبلغ تخفیف</label>
                <x-money-input wire:model.live="editDiscountAmount" class="form-control form-control-sm @error('editDiscountAmount') is-invalid @enderror" min="0" />
                @error('editDiscountAmount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">بیعانه</label>
                <x-money-input wire:model.live="editDepositAmount" class="form-control form-control-sm @error('editDepositAmount') is-invalid @enderror" min="0" />
                @error('editDepositAmount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">مبلغ کل</label>
                <input type="text" class="form-control form-control-sm fw-bold text-success" readonly value="{{ \App\Support\PdfPersian::toPersianDigits(number_format($this->editProgramTotalAmount)) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">باقیمانده</label>
                <input type="text" class="form-control form-control-sm fw-bold" readonly value="{{ \App\Support\PdfPersian::toPersianDigits(number_format($this->editProgramRemainingAmount)) }}">
            </div>
        </div>
        @else
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="text-muted small">قیمت پایه</div><div class="fw-bold">{{ \App\Support\PdfPersian::toPersianDigits(number_format($program->base_price)) }}</div></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="text-muted small">خدمات</div><div class="fw-bold">{{ \App\Support\PdfPersian::toPersianDigits(number_format($program->services_subtotal)) }}</div></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2"><div class="text-muted small">تخفیف</div><div class="fw-bold text-danger">{{ \App\Support\PdfPersian::toPersianDigits(number_format($program->discount_amount)) }}</div></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2 bg-light"><div class="text-muted small">مبلغ کل</div><div class="fw-bold text-success">{{ \App\Support\PdfPersian::toPersianDigits(number_format($program->total_amount)) }}</div></div></div>
            <div class="col-6 col-md-4"><div class="border rounded p-2"><div class="text-muted small">بیعانه</div><div class="fw-bold text-primary">{{ \App\Support\PdfPersian::toPersianDigits(number_format($program->deposit_amount)) }}</div></div></div>
            <div class="col-6 col-md-4"><div class="border rounded p-2"><div class="text-muted small">باقیمانده</div><div class="fw-bold">{{ \App\Support\PdfPersian::toPersianDigits(number_format($program->remainingAmount())) }}</div></div></div>
            @if($totalBeneficiaryDebt > 0)
            <div class="col-6 col-md-4"><div class="border rounded p-2"><div class="text-muted small">جمع بدهی ذینفعان</div><div class="fw-bold text-danger">{{ \App\Support\PdfPersian::toPersianDigits(number_format($totalBeneficiaryDebt)) }}</div></div></div>
            @endif
        </div>
        @endif

        @if($program->notes)
        <div class="mt-3 pt-3 border-top">
            <div class="text-muted small mb-1">یادداشت مالی</div>
            <div class="small">{{ $program->notes }}</div>
        </div>
        @endif
    </div>
</div>
