<div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                @if(session('status'))
                    <div class="alert alert-success py-1 small">{{ session('status') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger py-1 small">{{ $errors->first() }}</div>
                @endif
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">نام</label>
                        <input type="text" wire:model="name" class="form-control" placeholder="نام کاربر" @disabled(!$canEdit)>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">موبایل</label>
                        <input type="text" class="form-control bg-light" value="{{ $user->mobile }}" disabled>
                    </div>

                    @unless($user->isForeignGuestProfile())
                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">کد ملی</label>
                        <input type="text" wire:model="nationalId" class="form-control" placeholder="کد ملی ۱۰ رقمی" dir="ltr" @disabled(!$canEdit)>
                        @error('nationalId')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    @endunless

                    @if($user->isHost())
                    <div class="col-12 col-md-6">
                        @include('components.admin.host-position-select', ['positionOptions' => $hostPositionOptions, 'showSettingsLink' => false, 'disabled' => !$canEdit])
                    </div>
                    @endif

                    <div class="col-12">
                        <label class="form-label small text-muted fw-semibold"><i class="bi bi-shield-check me-1"></i>گروه ایثارگری (حداکثر ۲ گروه)</label>
                        <div class="row g-2">
                            @foreach($veteranGroups as $key => $group)
                            @if($key === '') @continue @endif
                            <div class="col-md-6">
                                <label class="d-flex align-items-center gap-2 border rounded p-3 {{ in_array((string)$key, $selectedVeteranTypes, true) ? 'border-primary bg-primary-subtle' : '' }}" style="cursor:{{ $canEdit ? 'pointer' : 'default' }};">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedVeteranTypes"
                                        value="{{ $key }}"
                                        class="form-check-input m-0"
                                        @disabled(!$canEdit || (count($selectedVeteranTypes) >= 2 && !in_array((string)$key, $selectedVeteranTypes, true)))
                                    >
                                    <div>
                                        <div class="fw-semibold">{{ $group['label'] }}</div>
                                        <div class="small text-muted">{{ $group['discount'] }}٪ تخفیف اقامت</div>
                                    </div>
                                </label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">درصد تخفیف اقامت</label>
                        <div class="input-group">
                            <input type="number" wire:model="discountPct" min="0" max="100" class="form-control" @disabled(!$canEdit || empty($selectedVeteranTypes))>
                            <button type="button" wire:click="syncDiscountFromGroup" class="btn btn-outline-secondary" @disabled(!$canEdit || empty($selectedVeteranTypes))>
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                        </div>
                        @error('discountPct')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    @if($user->hasAccountingProfile() && $provinces->isNotEmpty())
                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">استان (کدینگ حسابداری)</label>
                        <select wire:model.live="provinceId" class="form-select @error('provinceId') is-invalid @enderror" @disabled(!$canEdit)>
                            <option value="">انتخاب استان...</option>
                            @foreach($provinces as $province)
                                <option value="{{ $province->id }}">{{ $province->name }}</option>
                            @endforeach
                        </select>
                        @error('provinceId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    @if($this->accountingProvinceChangePending())
                    <div class="col-12">
                        <div class="alert alert-warning border-warning mb-0 small">
                            با تغییر استان، کد حسابداری از
                            <strong dir="ltr">{{ app(\App\Services\AccountingProvinceReassignmentService::class)->currentCodeForUser($user) }}</strong>
                            به
                            <strong dir="ltr">{{ $this->previewAccountingCodeAfterProvinceChange() }}</strong>
                            تغییر خواهد کرد.
                        </div>
                    </div>
                    @endif
                    @endif

                    @if($canEdit)
                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <a wire:navigate href="{{ route('host.users.show', $user) }}" class="btn btn-outline-secondary">انصراف</a>
                        @if($this->accountingProvinceChangePending())
                        <button
                            type="button"
                            wire:click="update"
                            data-swal-confirm="{{ $this->accountingProvinceChangeConfirmMessage() }}"
                            data-swal-confirm-title="هشدار تغییر کدینگ حسابداری"
                            data-swal-confirm-variant="warn"
                            class="btn btn-warning"
                        >
                            ذخیره با تغییر استان
                        </button>
                        @else
                        <button wire:click="update" class="btn btn-primary">ذخیره تغییرات</button>
                        @endif
                    </div>
                    @else
                    <div class="col-12">
                        <div class="alert alert-info mb-0 small">شما فقط مجوز مشاهده این صفحه را دارید و امکان ویرایش ندارید.</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        @if($user->isHost() && $canEdit)
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-shield-lock me-1"></i>رمز عبور پنل کاربر
            </div>
            <div class="card-body">
                @if(session('password_status'))
                    <div class="alert alert-success py-1 small">{{ session('password_status') }}</div>
                @endif
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <x-password-input label="رمز عبور جدید" wire:model="hostPassword" placeholder="حداقل ۶ کاراکتر" />
                        @error('hostPassword')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <x-password-input label="تکرار رمز عبور جدید" wire:model="hostPassword_confirmation" />
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="button" wire:click="updateHostPassword" class="btn btn-sm btn-outline-primary" wire:loading.attr="disabled">
                            <i class="bi bi-key me-1"></i>ذخیره رمز جدید
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-building me-1"></i>اقامتگاه‌های کاربر
            </div>
            <div class="card-body">
                @if($assignedAccommodations->isNotEmpty())
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>اقامتگاه</th><th>شهر</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($assignedAccommodations as $acc)
                            <tr wire:key="assigned-acc-{{ $acc->id }}">
                                <td class="small">{{ $acc->name }}</td>
                                <td class="small">{{ $acc->city->name ?? '—' }}</td>
                                <td class="text-end">
                                    <button type="button" wire:click="revokeAccommodation({{ $acc->id }})" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;">لغو دسترسی</button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif

                @if($availableAccommodations->isNotEmpty())
                <div class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label small text-muted">افزودن اقامتگاه</label>
                        <select wire:model="accommodationToAssign" class="form-select form-select-sm">
                            <option value="">انتخاب اقامتگاه...</option>
                            @foreach($availableAccommodations as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }} @if($acc->city)— {{ $acc->city->name }}@endif</option>
                            @endforeach
                        </select>
                        @error('accommodationToAssign')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <button type="button" wire:click="assignAccommodation" class="btn btn-sm btn-primary w-100">نسبت دادن</button>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

</div>
