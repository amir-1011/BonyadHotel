@props([
    'provinces' => [],
    'showAddEmployer' => false,
    'provinceHint' => 'پیش‌فرض از استان اقامتگاه انتخاب‌شده است؛ در صورت نیاز می‌توانید تغییر دهید.',
    'saveLabel' => 'ذخیره و انتخاب',
])

@if($showAddEmployer)
<div class="modal-backdrop fade show" style="z-index:1080;"></div>
<div class="modal fade show" style="display:block;z-index:1085;" tabindex="-1" role="dialog" wire:keydown.escape="closeEmployerModal">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-top:4px solid #0ea5e9;">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-info-subtle" style="width:36px;height:36px;">
                        <i class="bi bi-building-add text-info"></i>
                    </span>
                    افزودن کارفرما جدید
                </h5>
                <button type="button" class="btn-close" wire:click="closeEmployerModal" aria-label="بستن"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small mb-3">
                    کارفرمایان (ادارات و ارگان‌ها) در کل سامانه یکپارچه هستند. کد حسابداری بر اساس استان انتخاب‌شده صادر می‌شود.
                </div>
                <div class="row g-2">
                    <x-accounting.province-select
                        class="col-12"
                        :provinces="$provinces"
                        :show-code-preview="true"
                        :preview-code="$this->previewNextEmployerCode()"
                        indicator-label="شاخص ۴ (ارگان)"
                        :hint="$provinceHint"
                    />
                    <div class="col-md-6">
                        <label class="form-label small">نام کارفرما <span class="text-danger">*</span></label>
                        <input type="text" wire:model="newEmployerName" class="form-control form-control-sm">
                        @error('newEmployerName')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">کد ملی / شناسه اقتصادی <span class="text-danger">*</span></label>
                        <input type="text" wire:model="newEmployerNationalId" class="form-control form-control-sm">
                        @error('newEmployerNationalId')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">شماره همراه <span class="text-danger">*</span></label>
                        <input type="text" wire:model="newEmployerMobile" class="form-control form-control-sm" placeholder="09xxxxxxxxx">
                        @error('newEmployerMobile')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" wire:click="closeEmployerModal" class="btn btn-outline-secondary">انصراف</button>
                <button type="button" wire:click="addEmployerToCatalog" class="btn btn-info text-white">
                    <i class="bi bi-check-lg me-1"></i>{{ $saveLabel }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif
