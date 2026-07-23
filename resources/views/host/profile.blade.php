<div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-person-circle me-1"></i>اطلاعات حساب
            </div>
            <div class="card-body small">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="ta-user__avatar" style="width:48px;height:48px;font-size:1.25rem;">{{ mb_substr($user->name ?? 'M', 0, 1) }}</div>
                    <div>
                        <div class="fw-bold">{{ $user->name ?? '—' }}</div>
                        <div class="text-muted" dir="ltr">{{ $user->mobile }}</div>
                    </div>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">نقش</span>
                    <strong>{{ $user->hostRoleLabel() }}</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">رمز عبور</span>
                    <span class="badge {{ $user->hasPassword() ? 'bg-success' : 'bg-warning text-dark' }}">
                        {{ $user->hasPassword() ? 'تنظیم شده' : 'تنظیم نشده' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small">
                <i class="bi bi-shield-lock me-1"></i>تغییر رمز عبور
            </div>
            <div class="card-body">
                @if(session('status'))
                    <div class="alert alert-success py-2 small">{{ session('status') }}</div>
                @endif

                <form wire:submit="changePassword">
                    <div class="row g-3">
                        <div class="col-12">
                            <x-password-input label="رمز عبور فعلی" wire:model="currentPassword" placeholder="رمز عبور فعلی خود را وارد کنید" />
                            @error('currentPassword')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <x-password-input label="رمز عبور جدید" wire:model="password" placeholder="حداقل ۶ کاراکتر" />
                            @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <x-password-input label="تکرار رمز عبور جدید" wire:model="password_confirmation" placeholder="رمز جدید را مجدداً وارد کنید" />
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="changePassword"><i class="bi bi-check2-circle me-1"></i>ذخیره رمز جدید</span>
                                <span wire:loading wire:target="changePassword">در حال ذخیره...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

</div>
