<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
</div>

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
                        <input type="text" wire:model="name" class="form-control" placeholder="نام میزبان">
                        @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">موبایل <span class="text-danger">*</span></label>
                        <input type="text" wire:model="mobile" class="form-control" placeholder="09123456789" dir="ltr">
                        @error('mobile')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="form-text">برای ورود به پنل میزبان (در کنار رمز عبور) استفاده می‌شود.</div>
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
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-shield-lock me-1"></i>رمز عبور پنل میزبان
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
                <i class="bi bi-sliders me-1"></i>دسترسی‌های پنل میزبان
            </div>
            <div class="card-body">
                <x-admin.host-permissions-matrix
                    :catalog="$hostPermissionCatalog"
                    :form-state="$hostPermissionForm"
                />
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-building me-1"></i>اقامتگاه‌های میزبان
            </div>
            <div class="card-body">
                @error('selectedAccommodationIds')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror

                @if($accommodations->isNotEmpty())
                <p class="text-muted small mb-3">اقامتگاه‌هایی که این میزبان باید به آن‌ها دسترسی داشته باشد. می‌توانید بعداً هم از صفحه ویرایش کاربر تغییر دهید.</p>
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
                                    <span class="text-muted">({{ $acc->hosts_count }} میزبان دیگر)</span>
                                @endif
                            </span>
                        </label>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted small mb-0">هنوز اقامتگاهی در سیستم ثبت نشده است. می‌توانید میزبان را بدون اقامتگاه ایجاد کنید و بعداً نسبت دهید.</p>
                @endif
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end mt-3">
            <a wire:navigate href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">انصراف</a>
            <button wire:click="save" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save"><i class="bi bi-check2-circle me-1"></i>ایجاد میزبان</span>
                <span wire:loading wire:target="save">در حال ذخیره...</span>
            </button>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small">راهنما</div>
            <div class="card-body small text-muted">
                <p class="mb-2">پس از ایجاد، میزبان می‌تواند با <strong>موبایل</strong> و <strong>رمز عبور</strong> از صفحه ورود پرسنل وارد پنل شود.</p>
                <p class="mb-2">دسترسی‌های پنل برای هر صفحه به‌صورت جداگانه (مشاهده، ایجاد، ویرایش، حذف) تعیین می‌شود.</p>
                <p class="mb-0">اقامتگاه‌های انتخاب‌شده محدوده داده‌های میزبان (رزرو، اتاق، برنامه و ...) را مشخص می‌کنند.</p>
            </div>
        </div>
    </div>
</div>

</div>
