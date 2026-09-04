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
<x-accounting.add-beneficiary-modal
    :provinces="$provinces ?? collect()"
    :show-add-beneficiary="$showAddBeneficiary"
    info-message="ذینفعان در کل سامانه یکپارچه هستند. پس از ثبت، در صورت امکان به‌عنوان کاربر سیستم نیز ثبت می‌شوند."
/>
@endif
