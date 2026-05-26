<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('admin.users.show', $user) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <h5 class="fw-bold mb-0">ویرایش اطلاعات {{ $user->name ?? $user->mobile }}</h5>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger py-1 small">{{ $errors->first() }}</div>
                @endif
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">نام</label>
                        <input type="text" wire:model="name" class="form-control" placeholder="نام کاربر">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">موبایل</label>
                        <input type="text" class="form-control bg-light" value="{{ $user->mobile }}" disabled>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">کد ملی</label>
                        <input type="text" wire:model="nationalId" class="form-control" placeholder="کد ملی ۱۰ رقمی">
                        <div class="form-text">با تغییر کد ملی، نوع ایثارگری و درصد تخفیف به‌صورت خودکار به‌روز می‌شود.</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small text-muted">نقش</label>
                        <select wire:model="role" class="form-select">
                            @foreach($roles as $r)
                                <option value="{{ $r->name }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info mb-0">
                            <div class="fw-semibold mb-1">وضعیت فعلی</div>
                            <div class="small">موبایل: <span class="badge {{ $user->mobile_verified_at ? 'bg-success' : 'bg-danger' }}">{{ $user->mobile_verified_at ? 'تأیید شده' : 'تأیید نشده' }}</span></div>
                            <div class="small mt-1">کد ملی: <span class="badge {{ $user->national_id_verified_at ? 'bg-success' : 'bg-secondary' }}">{{ $user->national_id_verified_at ? 'تأیید شده' : 'تأیید نشده' }}</span></div>
                        </div>
                    </div>

                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <a wire:navigate href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-secondary">انصراف</a>
                        <button wire:click="update" class="btn btn-primary">ذخیره تغییرات</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small">خلاصه کاربر</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">نقش‌ها</span><strong>{{ $user->roles->pluck('name')->join('، ') ?: 'guest' }}</strong></div>
                <div class="d-flex justify-content-between mb-2"><span class="text-muted">نوع ایثارگر</span><strong>{{ $user->veteranLabel() }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-muted">تخفیف</span><strong>{{ $user->discount_percentage }}%</strong></div>
            </div>
        </div>
    </div>
</div>

</div>