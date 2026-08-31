@props(['beneficiaries'])

<div class="card shadow-sm">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-building me-2"></i>ذینفعان و هزینه‌ها</span>
        <button type="button" wire:click="addBeneficiaryRow" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus"></i> ردیف</button>
    </div>
    <div class="card-body">
        <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            ذینفعان در کل سامانه یکپارچه‌اند. با ثبت ذینفع جدید، در صورت امکان یک حساب کاربری نیز ایجاد یا متصل می‌شود تا تاریخچه رزروها و بدهی‌ها قابل پیگیری باشد.
        </div>

        @if($beneficiaryRows === [])
        <div class="alert alert-light border small mb-3">می‌توانید ذینفعان و بدهی‌های این رزرو را ثبت کنید (اختیاری).</div>
        <button type="button" wire:click="addBeneficiaryRow" class="btn btn-sm btn-primary">افزودن ذینفع</button>
        @endif

        @foreach($beneficiaryRows as $bi => $row)
        <div class="border rounded p-3 mb-3" wire:key="ben-row-{{ $bi }}">
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label small mb-1">ذینفع</label>
                    <select wire:model="beneficiaryRows.{{ $bi }}.program_beneficiary_id" class="form-select form-select-sm">
                        <option value="">— انتخاب —</option>
                        @foreach($beneficiaries as $b)
                            <option value="{{ $b->id }}">{{ $b->displayLabel() }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="openBeneficiaryModal({{ $bi }})" class="btn btn-link btn-sm p-0 text-decoration-none mt-1">
                        <i class="bi bi-plus-circle me-1"></i>ذینفع در لیست نیست؟ افزودن
                    </button>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">میزان بدهی (ریال)</label>
                    <x-money-input wire:model="beneficiaryRows.{{ $bi }}.debt_amount" class="form-control form-control-sm" min="0" />
                </div>
                <div class="col-md-4">
                    <label class="form-label small mb-1">مدارک ضمیمه (PDF یا تصویر)</label>
                    <input type="file" wire:model="beneficiaryRows.{{ $bi }}.documents" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" class="form-control form-control-sm">
                    @error('beneficiaryRows.'.$bi.'.documents.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">توضیح</label>
                    <textarea wire:model="beneficiaryRows.{{ $bi }}.description" rows="2" class="form-control form-control-sm"></textarea>
                </div>
            </div>
            <div class="text-end mt-2">
                <button type="button" wire:click="removeBeneficiaryRow({{ $bi }})" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </div>
        </div>
        @endforeach
    </div>
</div>

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
                    ذینفعان در کل سامانه یکپارچه هستند. پس از ثبت، در صورت امکان به‌عنوان کاربر سیستم نیز ثبت می‌شوند.
                </div>
                <div class="row g-2">
                    <x-accounting.province-select
                        class="col-12"
                        :provinces="$provinces ?? collect()"
                        :show-code-preview="true"
                        :preview-code="$this->previewNextBeneficiaryCode()"
                        indicator-label="شاخص ۱ (ذینفع)"
                        hint="پیش‌فرض از استان اقامتگاه انتخاب‌شده است؛ در صورت نیاز می‌توانید تغییر دهید."
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
                    <i class="bi bi-check-lg me-1"></i>ذخیره و انتخاب
                </button>
            </div>
        </div>
    </div>
</div>
@endif
