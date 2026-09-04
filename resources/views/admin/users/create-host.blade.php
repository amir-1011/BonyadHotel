<div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-person-badge me-1"></i>اطلاعات پایه
            </div>
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger py-1 small">{{ $errors->first() }}</div>
                @endif

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">نام <span class="text-danger">*</span></label>
                        <input type="text" wire:model="name" class="form-control" placeholder="نام کاربر">
                        @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">موبایل <span class="text-danger">*</span></label>
                        <input type="text" wire:model="mobile" class="form-control" placeholder="09123456789" dir="ltr">
                        @error('mobile')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="form-text">برای ورود به پنل کاربر (در کنار رمز عبور) استفاده می‌شود.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">کد ملی</label>
                        <input type="text" wire:model="nationalId" class="form-control" placeholder="کد ملی ۱۰ رقمی" dir="ltr">
                        @error('nationalId')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="form-text">در صورت وارد کردن، گروه ایثارگری از سرویس استعلام بررسی می‌شود.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        @include('components.admin.host-position-select', ['positionOptions' => $hostPositionOptions])
                    </div>

                    <x-accounting.province-select
                        :provinces="$provinces"
                        :show-code-preview="true"
                        :preview-code="$this->previewNextPersonnelCode()"
                        indicator-label="شاخص ۷ (پرسنل)"
                        hint="پیش‌فرض از استان اولین اقامتگاه انتخاب‌شده است؛ در صورت نیاز می‌توانید تغییر دهید."
                    />
                </div>

                <div class="alert alert-light border small mt-3 mb-0">
                    <i class="bi bi-upc-scan me-1 text-primary"></i>
                    کد پرسنلی بر اساس <strong>استان انتخاب‌شده</strong> خودکار تخصیص می‌یابد:
                    <strong dir="ltr">{{ $this->previewNextPersonnelCode() }}</strong>
                    <span class="text-muted">— {{ $this->previewPersonnelProvinceLabel() }}</span>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-shield-lock me-1"></i>رمز عبور پنل کاربر
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <x-password-input label="رمز عبور *" wire:model="hostPassword" placeholder="حداقل ۶ کاراکتر" />
                        @error('hostPassword')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <x-password-input label="تکرار رمز عبور *" wire:model="hostPassword_confirmation" placeholder="رمز را مجدداً وارد کنید" />
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-building me-1"></i>اقامتگاه‌های کاربر
            </div>
            <div class="card-body">
                @error('selectedAccommodationIds')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror

                @if($accommodations->isNotEmpty())
                <p class="text-muted small mb-3">حداقل یک اقامتگاه انتخاب کنید. استان پیش‌فرض کدینگ از اولین اقامتگاه انتخاب‌شده تعیین می‌شود و قابل تغییر است.</p>
                <div class="row g-2">
                    @foreach($accommodations as $acc)
                    <div class="col-12">
                        <label class="d-flex align-items-center gap-2 border rounded p-2 mb-0" style="cursor:pointer" wire:key="acc-option-{{ $acc->id }}">
                            <input type="checkbox" class="form-check-input" wire:model="selectedAccommodationIds" value="{{ $acc->id }}">
                            <span class="small flex-grow-1">
                                <span class="fw-semibold">{{ $acc->name }}</span>
                                @if($acc->city)
                                    <span class="text-muted">— {{ $acc->city->name }}</span>
                                @endif
                                <span class="badge bg-{{ $acc->is_active ? 'success' : 'secondary' }} ms-1">{{ $acc->is_active ? 'فعال' : 'غیرفعال' }}</span>
                                @if($acc->hosts_count > 0)
                                    <span class="text-muted">({{ $acc->hosts_count }} کاربر دیگر)</span>
                                @endif
                            </span>
                        </label>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted small mb-0">هنوز اقامتگاهی در سیستم ثبت نشده است. می‌توانید کاربر را بدون اقامتگاه ایجاد کنید و بعداً نسبت دهید.</p>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end mt-3">
            <a wire:navigate href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">انصراف</a>
            <button wire:click="save" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save"><i class="bi bi-check2-circle me-1"></i>ایجاد کاربر</span>
                <span wire:loading wire:target="save">در حال ذخیره...</span>
            </button>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small">راهنما</div>
            <div class="card-body small text-muted">
                <p class="mb-2">پس از ایجاد، کاربر می‌تواند با <strong>موبایل</strong> و <strong>رمز عبور</strong> از صفحه ورود پرسنل وارد پنل شود.</p>
                <p class="mb-2">دسترسی‌های پنل بر اساس <strong>سمت</strong> انتخاب‌شده از <a href="{{ route('admin.host-positions.index') }}" wire:navigate>تنظیمات سمت‌ها</a> اعمال می‌شوند.</p>
                <p class="mb-2">کد پرسنلی از <strong>اولین اقامتگاه</strong> انتخاب‌شده (بر اساس استان شهر/شهرستان) به‌صورت خودکار صادر می‌شود.</p>
                <p class="mb-0">اقامتگاه‌های انتخاب‌شده محدوده داده‌های کاربر (رزرو، اتاق، برنامه و ...) را مشخص می‌کنند.</p>
            </div>
        </div>
    </div>
</div>

</div>
