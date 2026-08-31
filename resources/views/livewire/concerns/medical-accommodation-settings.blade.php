<div wire:key="medical-accommodation-{{ $accommodation->id }}">

@php
    $canEditMedical = ($panel ?? 'admin') !== 'host'
        || auth()->user()?->hostCan('accommodations.medical-accommodation', 'edit');
@endphp

@if(($panel ?? 'admin') === 'host' && !$canEditMedical)
<div class="alert alert-warning small"><i class="bi bi-lock me-1"></i>فقط مجوز مشاهده دارید — امکان تغییر تنظیمات اسکان درمانی وجود ندارد.</div>
@endif

<form wire:submit="saveSettings" class="card shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <span>تنظیمات عمومی و کارفرما</span>
        @if($canEditMedical)
        <button type="button" wire:click="restoreDefaultMedicalAccommodation" data-swal-confirm="تعرفه‌ها و تنظیمات این اقامتگاه به پیش‌فرض قرارداد بیمه دی بازگردانی شود؟" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-arrow-counterclockwise me-1"></i>بازگردانی پیش‌فرض بیمه دی
        </button>
        @endif
    </div>
    <div class="card-body row g-3">
        <div class="col-12">
            <p class="text-muted small mb-2">
                تعرفه‌ها، سقف همراه و کارفرمای پرداخت‌کننده (بیمه دی) برای اقامتگاه <strong>{{ $accommodation->name }}</strong>.
                در رزرو درمانی مهمان وجه اقامت را نمی‌پردازد، مبلغ به‌صورت بدهی کارفرما ثبت می‌شود و سیاست کنسلی/جریمه اعمال نمی‌گردد.
            </p>
            <div class="alert alert-info small mb-0">
                <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>منطق اجرایی</div>
                <ul class="mb-0 ps-3">
                    <li>صدور معرفی‌نامه و تطبیق وضعیت بیمار با نوع تعرفه</li>
                    <li>محاسبه شبانه بر اساس تعرفه انتخاب‌شده (نه نرخ اتاق)</li>
                    <li>تمدید تاریخ → افزایش مبلغ فاکتور / خروج زودتر → کاهش مبلغ بدون جریمه</li>
                    <li>اقامت روزانه (بدون شب) تحت پوشش بیمه دی نیست</li>
                </ul>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label small fw-semibold">کارفرما / بیمه‌گر <span class="text-danger">*</span></label>
            <select wire:model="programEmployerId" class="form-select @error('programEmployerId') is-invalid @enderror" @disabled(!$canEditMedical)>
                <option value="">— انتخاب کنید —</option>
                @foreach($employers as $employer)
                    <option value="{{ $employer->id }}">{{ $employer->displayLabel() }}</option>
                @endforeach
            </select>
            @error('programEmployerId') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            @if($canEditMedical)
            <button type="button" wire:click="openEmployerModal" class="btn btn-link btn-sm p-0 text-decoration-none mt-1">
                <i class="bi bi-plus-circle me-1"></i>کارفرما در لیست نیست؟ افزودن
            </button>
            @endif
            <div class="form-text">کارفرمای پیش‌فرض برای قراردادهای جدید (معمولاً بیمه دی همان استان).</div>
        </div>
        <div class="col-12">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" wire:model="skipCancellationPenalties" id="skipCancellationPenalties" @disabled(!$canEditMedical)>
                <label class="form-check-label" for="skipCancellationPenalties">عدم اعمال سیاست کنسلی و جریمه (کاهش تاریخ اقامت بدون جریمه)</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="requireOvernight" id="requireOvernight" @disabled(!$canEditMedical)>
                <label class="form-check-label" for="requireOvernight">الزام اقامت شبانه (عدم پوشش رزرو روزانه)</label>
                <x-admin.column-help class="ms-1 align-middle" title="الزام اقامت شبانه">
                    اسکان درمانی بر اساس <strong>تعرفه شبانه</strong> بیمه دی محاسبه می‌شود، نه استفاده روزانه (بدون شب).
                    <ul class="mt-2">
                        <li>با فعال بودن این گزینه، رزرو درمانی فقط وقتی ثبت می‌شود که حداقل <strong>یک شب</strong> اقامت وجود داشته باشد.</li>
                        <li>ورود و خروج در همان تاریخ (صفر شب / رزرو روزانه) تحت پوشش بیمه دی نیست و سیستم آن را رد می‌کند.</li>
                        <li>مثال مجاز: ورود ۱۰ اردیبهشت و خروج ۱۱ اردیبهشت = یک شب.</li>
                        <li>مثال غیرمجاز: ورود و خروج هر دو در ۱۰ اردیبهشت = صفر شب.</li>
                    </ul>
                </x-admin.column-help>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label small fw-semibold">یادداشت داخلی</label>
            <textarea wire:model="notes" class="form-control" rows="2" @disabled(!$canEditMedical)></textarea>
        </div>
        @if($canEditMedical)
        <div class="col-12">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>ذخیره تنظیمات</button>
        </div>
        @endif
    </div>
</form>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div>
        <h6 class="fw-semibold mb-0">قراردادها</h6>
        <div class="text-muted small">هر قرارداد شماره، بازه اعتبار و تعرفهٔ خودش را دارد. در رزرو دستی شماره قرارداد انتخاب می‌شود.</div>
    </div>
    @if($canEditMedical)
    <button type="button" wire:click="addContract" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-plus-lg me-1"></i>افزودن قرارداد
    </button>
    @endif
</div>

@forelse($contracts as $cIndex => $contract)
<div class="card shadow-sm mb-3" wire:key="med-contract-{{ $contract['id'] ?? ('new-'.$cIndex) }}">
    <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="fw-semibold">
            قرارداد
            <span dir="ltr" class="font-monospace">{{ $contract['contract_number'] }}</span>
            @if(empty($contract['is_active']))
                <span class="badge text-bg-secondary">غیرفعال</span>
            @endif
        </div>
        @if($canEditMedical && count($contracts) > 1)
        <button type="button" wire:click="removeContract({{ $cIndex }})" data-swal-confirm="این قرارداد و تعرفه‌هایش حذف شود؟" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-trash me-1"></i>حذف قرارداد
        </button>
        @endif
    </div>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label small fw-semibold">شماره قرارداد <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="text" wire:model="contracts.{{ $cIndex }}.contract_number" class="form-control font-monospace @error('contracts.'.$cIndex.'.contract_number') is-invalid @enderror" dir="auto" @disabled(!$canEditMedical || !empty($contract['number_locked']))>
                @if($canEditMedical && !empty($contract['number_locked']))
                <button type="button" class="btn btn-outline-secondary" wire:click="unlockContractNumber({{ $cIndex }})" data-swal-confirm="شماره قرارداد به‌صورت خودکار توسط سامانه صادر شده است. تغییر آن ممکن است با اسناد و مکاتبات رسمی ناهماهنگ شود. ادامه می‌دهید؟" data-swal-confirm-variant="warn" title="تغییر شماره">
                    <i class="bi bi-pencil"></i>
                </button>
                @endif
            </div>
            @error('contracts.'.$cIndex.'.contract_number') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            <div class="form-text">پیش‌فرض: سال + کد استان + شماره ترتیبی (مثل ۱۴۰۵۵۰۲۰۱)</div>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">کارفرما / بیمه‌گر <span class="text-danger">*</span></label>
            <select wire:model="contracts.{{ $cIndex }}.program_employer_id" class="form-select @error('contracts.'.$cIndex.'.program_employer_id') is-invalid @enderror" @disabled(!$canEditMedical)>
                <option value="">— انتخاب کنید —</option>
                @foreach($employers as $employer)
                    <option value="{{ $employer->id }}">{{ $employer->displayLabel() }}</option>
                @endforeach
            </select>
            @error('contracts.'.$cIndex.'.program_employer_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">شروع (شمسی)</label>
            <input type="text" wire:model="contracts.{{ $cIndex }}.starts_on_jalali" class="form-control @error('contracts.'.$cIndex.'.starts_on_jalali') is-invalid @enderror" dir="ltr" placeholder="۱۴۰۵/۰۲/۰۱" @disabled(!$canEditMedical)>
            @error('contracts.'.$cIndex.'.starts_on_jalali') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">پایان (شمسی)</label>
            <input type="text" wire:model="contracts.{{ $cIndex }}.ends_on_jalali" class="form-control @error('contracts.'.$cIndex.'.ends_on_jalali') is-invalid @enderror" dir="ltr" placeholder="۱۴۰۶/۰۱/۳۱" @disabled(!$canEditMedical)>
            @error('contracts.'.$cIndex.'.ends_on_jalali') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" wire:model="contracts.{{ $cIndex }}.is_active" id="contractActive{{ $cIndex }}" @disabled(!$canEditMedical)>
                <label class="form-check-label" for="contractActive{{ $cIndex }}">قرارداد فعال است و در رزرو دستی نمایش داده می‌شود</label>
            </div>
        </div>

        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                <span class="small fw-semibold">تعرفه‌های این قرارداد</span>
                @if($canEditMedical)
                <div class="d-flex gap-2 align-items-center">
                    <select wire:model="contracts.{{ $cIndex }}.new_tariff_template" class="form-select form-select-sm" style="width:auto">
                        <option value="">تعرفه سفارشی</option>
                        @foreach($templates as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="addTariff({{ $cIndex }})" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>افزودن تعرفه</button>
                </div>
                @endif
            </div>
            <div class="table-responsive border rounded">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>عنوان تعرفه</th>
                            <th style="width:140px">نرخ شبانه</th>
                            <th style="width:140px">نرخ همراه / شب</th>
                            <th style="width:90px" class="d-none">همراه مشمول</th>
                            <th style="width:90px">سقف همراه</th>
                            <th>توضیح</th>
                            <th style="width:60px">فعال</th>
                            @if($canEditMedical)
                            <th style="width:50px"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contract['tariffs'] as $tIndex => $tariff)
                        <tr wire:key="med-tariff-{{ $cIndex }}-{{ $tariff['id'] ?? ('new-'.$tIndex) }}">
                            <td>
                                <input type="text" wire:model="contracts.{{ $cIndex }}.tariffs.{{ $tIndex }}.label" class="form-control form-control-sm @error('contracts.'.$cIndex.'.tariffs.'.$tIndex.'.label') is-invalid @enderror" @disabled(!$canEditMedical)>
                                @error('contracts.'.$cIndex.'.tariffs.'.$tIndex.'.label') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </td>
                            <td>
                                <x-money-input wire:model="contracts.{{ $cIndex }}.tariffs.{{ $tIndex }}.nightly_rate" class="form-control form-control-sm" min="0" />
                                @error('contracts.'.$cIndex.'.tariffs.'.$tIndex.'.nightly_rate') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </td>
                            <td>
                                <x-money-input wire:model="contracts.{{ $cIndex }}.tariffs.{{ $tIndex }}.companion_nightly_rate" class="form-control form-control-sm" min="0" />
                                @error('contracts.'.$cIndex.'.tariffs.'.$tIndex.'.companion_nightly_rate') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </td>
                            <td class="d-none">
                                <input type="number" min="0" max="10" wire:model="contracts.{{ $cIndex }}.tariffs.{{ $tIndex }}.companions_included" class="form-control form-control-sm" @disabled(!$canEditMedical)>
                                @error('contracts.'.$cIndex.'.tariffs.'.$tIndex.'.companions_included') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </td>
                            <td>
                                <input type="number" min="0" max="10" wire:model="contracts.{{ $cIndex }}.tariffs.{{ $tIndex }}.max_companions" class="form-control form-control-sm" @disabled(!$canEditMedical)>
                                @error('contracts.'.$cIndex.'.tariffs.'.$tIndex.'.max_companions') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </td>
                            <td>
                                <input type="text" wire:model="contracts.{{ $cIndex }}.tariffs.{{ $tIndex }}.notes" class="form-control form-control-sm" @disabled(!$canEditMedical)>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" wire:model="contracts.{{ $cIndex }}.tariffs.{{ $tIndex }}.is_active" @disabled(!$canEditMedical)>
                            </td>
                            @if($canEditMedical)
                            <td>
                                <button type="button" wire:click="removeTariff({{ $cIndex }}, {{ $tIndex }})" data-swal-confirm="این تعرفه حذف شود؟" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">تعرفه‌ای برای این قرارداد تعریف نشده است.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($canEditMedical)
        <div class="col-12">
            <button type="button" wire:click="saveContract({{ $cIndex }})" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>ذخیره این قرارداد</button>
        </div>
        @endif
    </div>
</div>
@empty
<div class="alert alert-warning">هنوز قراردادی ثبت نشده است.</div>
@endforelse

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
                        hint="پیش‌فرض از استان این اقامتگاه است؛ در صورت نیاز می‌توانید تغییر دهید."
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
                    <i class="bi bi-check-lg me-1"></i>ذخیره و انتخاب
                </button>
            </div>
        </div>
    </div>
</div>
@endif

</div>
