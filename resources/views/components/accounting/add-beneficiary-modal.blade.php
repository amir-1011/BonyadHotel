@props([
    'provinces' => [],
    'showAddBeneficiary' => false,
    'provinceHint' => 'پیش‌فرض از استان اقامتگاه انتخاب‌شده است؛ در صورت نیاز می‌توانید تغییر دهید.',
    'infoMessage' => 'ذینفعان در کل سامانه یکپارچه هستند. کد حسابداری بر اساس استان انتخاب‌شده صادر می‌شود.',
    'saveLabel' => 'ذخیره و انتخاب',
])

@if($showAddBeneficiary)
<div class="modal-backdrop fade show" style="z-index:1080;"></div>
<div class="modal fade show" style="display:block;z-index:1085;" tabindex="-1" role="dialog" wire:keydown.escape="closeBeneficiaryModal">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-top:4px solid #22c55e;">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle" style="width:36px;height:36px;">
                        <i class="bi bi-person-plus-fill text-success"></i>
                    </span>
                    افزودن ذینفع جدید
                </h5>
                <button type="button" class="btn-close" wire:click="closeBeneficiaryModal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    {{ $infoMessage }}
                </div>
                <div class="row g-2">
                    <x-accounting.province-select
                        class="col-12"
                        :provinces="$provinces"
                        :show-code-preview="true"
                        :preview-code="$this->previewNextBeneficiaryCode()"
                        indicator-label="شاخص ۱ (ذینفع)"
                        :hint="$provinceHint"
                    />
                    <div class="col-md-6">
                        <label class="form-label small">نام ذینفع <span class="text-danger">*</span></label>
                        <input type="text" wire:model="newBeneficiaryName" class="form-control form-control-sm">
                        @error('newBeneficiaryName')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">کد ملی / شناسه اقتصادی <span class="text-danger">*</span></label>
                        <input type="text" wire:model="newBeneficiaryNationalId" class="form-control form-control-sm">
                        @error('newBeneficiaryNationalId')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">شماره همراه <span class="text-danger">*</span></label>
                        <input type="text" wire:model="newBeneficiaryMobile" class="form-control form-control-sm" placeholder="09xxxxxxxxx">
                        @error('newBeneficiaryMobile')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" wire:click="closeBeneficiaryModal" class="btn btn-outline-secondary">انصراف</button>
                <button type="button" wire:click="addBeneficiaryToCatalog" class="btn btn-success">
                    <i class="bi bi-check-lg me-1"></i>{{ $saveLabel }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif
